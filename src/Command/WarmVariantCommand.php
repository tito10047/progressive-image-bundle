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
use Tito10047\ProgressiveImageBundle\Variant\Application\Command\GenerateVariant;
use Tito10047\ProgressiveImageBundle\Variant\Application\Handler\GenerateVariantHandler;
use Tito10047\ProgressiveImageBundle\Variant\Application\Service\FilterSetRegistry;
use Tito10047\ProgressiveImageBundle\Variant\Application\Service\VariantSpecFactory;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\SourcePath;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\Variant;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Port\VariantStorage;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Service\VariantIdHasher;

/**
 * Pre-generates variants for a source ahead of the first real request — CI/deploy hooks,
 * bulk-import scripts, or just avoiding a cold first hit for a known-important image.
 * Always generates synchronously and reports per filter set, regardless of
 * generation.strategy (a warm command that just queues work and exits isn't "warm" yet).
 */
#[AsCommand(
    name: 'progressive-image:variant:warm',
    description: 'Pre-generates variants for a source image across one or more filter sets.',
)]
final class WarmVariantCommand extends Command
{
    public function __construct(
        private readonly VariantSpecFactory $specFactory,
        private readonly VariantIdHasher $hasher,
        private readonly VariantStorage $storage,
        private readonly GenerateVariantHandler $generateHandler,
        private readonly FilterSetRegistry $filterSets,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('source', InputArgument::REQUIRED, 'The logical source path to warm (e.g. uploads/hero.jpg).')
            ->addOption('filter-set', 'f', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'A filter_sets name to warm. Repeatable. Defaults to every configured filter set when omitted.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $sourcePath = new SourcePath((string) $input->getArgument('source'));

        /** @var list<string> $filterSetNames */
        $filterSetNames = $input->getOption('filter-set');
        if ([] === $filterSetNames) {
            $filterSetNames = $this->filterSets->names();
        }

        if ([] === $filterSetNames) {
            $io->warning('No filter sets are configured — nothing to warm.');

            return Command::SUCCESS;
        }

        $generated = 0;
        $alreadyExisted = 0;
        $failed = 0;

        foreach ($filterSetNames as $filterSetName) {
            $spec = $this->specFactory->createFromFilterSet($filterSetName);
            $variant = Variant::request($sourcePath, $spec, $this->hasher);
            $path = $variant->path();

            if ($this->storage->exists($path)) {
                ++$alreadyExisted;
                $io->writeln(sprintf(' <comment>already exists</comment> %s -> %s', $filterSetName, $this->storage->publicPath($path)));
                continue;
            }

            try {
                ($this->generateHandler)(new GenerateVariant($sourcePath, $spec));
            } catch (\Throwable) {
                // GenerateVariantHandler already recorded the fail marker and published
                // VariantGenerationFailed — nothing more to do here than count it and keep
                // processing the remaining filter sets instead of aborting the whole warm.
            }

            if ($this->storage->exists($path)) {
                ++$generated;
                $io->writeln(sprintf(' <info>generated</info> %s -> %s', $filterSetName, $this->storage->publicPath($path)));
            } else {
                ++$failed;
                $io->writeln(sprintf(' <error>failed</error> %s', $filterSetName));
            }
        }

        $message = sprintf(
            'Warmed "%s": %d generated, %d already existed, %d failed (%d filter set(s) total).',
            $sourcePath->value,
            $generated,
            $alreadyExisted,
            $failed,
            \count($filterSetNames)
        );

        if ($failed > 0) {
            $io->error($message);

            return Command::FAILURE;
        }

        $io->success($message);

        return Command::SUCCESS;
    }
}
