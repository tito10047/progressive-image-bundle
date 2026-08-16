<?php

/*
 * This file is part of the Progressive Image Bundle.
 *
 * (c) Jozef Môstka <https://github.com/tito10047/progressive-image-bundle>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tito10047\ProgressiveImageBundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\SourcePath;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Port\VariantStorage;

/**
 * Purges every stored variant for one source path — e.g. after replacing/deleting the
 * original, so stale sizes don't keep being served from storage forever (content-addressed
 * generation means a changed source naturally gets a new VariantId, but the old variants for
 * the previous bytes are never cleaned up on their own).
 *
 * Scoped to a single source only: VariantStorage has no "list everything" capability (by
 * design — see list()'s own docblock), only "list everything for this source". A global
 * purge would need a different port method and is intentionally left out of this first
 * pass.
 */
#[AsCommand(
    name: 'progressive-image:variant:remove',
    description: 'Deletes every stored variant for a source image.',
)]
final class RemoveVariantCommand extends Command
{
    public function __construct(
        private readonly VariantStorage $storage,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('source', InputArgument::REQUIRED, 'The logical source path to purge (e.g. uploads/hero.jpg).')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'List what would be deleted without deleting anything.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $sourcePath = new SourcePath((string) $input->getArgument('source'));
        $dryRun = (bool) $input->getOption('dry-run');

        $count = 0;
        foreach ($this->storage->list($sourcePath) as $variantPath) {
            ++$count;

            if ($dryRun) {
                $io->writeln(sprintf(' <comment>would delete</comment> %s', $variantPath->value));
                continue;
            }

            $this->storage->delete($variantPath);
            $io->writeln(sprintf(' <info>deleted</info> %s', $variantPath->value));
        }

        if (0 === $count) {
            $io->success(sprintf('No stored variants found for "%s".', $sourcePath->value));

            return Command::SUCCESS;
        }

        $io->success($dryRun
            ? sprintf('%d variant(s) would be deleted for "%s" (dry run).', $count, $sourcePath->value)
            : sprintf('Deleted %d variant(s) for "%s".', $count, $sourcePath->value));

        return Command::SUCCESS;
    }
}
