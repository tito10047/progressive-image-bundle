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
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

#[AsCommand(
    name: 'progressive-image:generate-custom-css',
    description: 'Generates a custom Tailwind CSS file based on the bundle configuration.',
)]
class GenerateCustomCssCommand extends Command
{
    /**
     * @param array{
     *     layouts: array<string, array{
     *         min_viewport: int,
     *         max_container: int|null
     *     }>,
     *     columns: int
     * } $gridConfig
     */
    public function __construct(
        private readonly array $gridConfig,
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('path', InputArgument::OPTIONAL, 'Path to the directory where the CSS file will be generated.', 'assets/styles');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $path = $input->getArgument('path');

        if (!str_starts_with($path, '/')) {
            $path = $this->projectDir.'/'.$path;
        }

        $filesystem = new Filesystem();
        if (!$filesystem->exists($path)) {
            $filesystem->mkdir($path);
        }

        $filePath = $path.'/progressive-image-custom.css';

        $cssContent = $this->generateCssContent();

        $filesystem->dumpFile($filePath, $cssContent);

        $io->success(sprintf('CSS file generated at %s', $filePath));

        return Command::SUCCESS;
    }

    private function generateCssContent(): string
    {
        $layouts = $this->gridConfig['layouts'];

        // Sort layouts by min_viewport descending for the root .progressive-image-container nested variables
        $sortedLayouts = $layouts;
        uasort($sortedLayouts, fn ($a, $b) => $b['min_viewport'] <=> $a['min_viewport']);

        $content = "/* Progressive Image Container - Custom Breakpoints */\n";
        $content = "@layer vendor {\n";
        $content .= "\t.progressive-image-container {\n";
        $content .= "\t\tdisplay: block;\n";

        // Root width variable with fallbacks
        $content .= "\t\twidth: ".$this->generateVariableFallback($sortedLayouts, 'width').";\n";

        // Root aspect-ratio variable with fallbacks
        $content .= "\t\taspect-ratio: ".$this->generateVariableFallback($sortedLayouts, 'aspect').";\n";

        $content .= "\t\tposition: relative;\n";
        $content .= "\t\toverflow: hidden;\n";
        $content .= "\t}\n\n";

        // Media queries - sort by min_viewport ascending
        $mediaLayouts = $layouts;
        uasort($mediaLayouts, fn ($a, $b) => $a['min_viewport'] <=> $b['min_viewport']);

        foreach ($mediaLayouts as $name => $layout) {
            if (0 === $layout['min_viewport']) {
                continue;
            }

            $content .= sprintf("/* %s: %dpx */\n", $name, $layout['min_viewport']);
            $content .= sprintf("@media (min-width: %dpx) {\n", $layout['min_viewport']);
            $content .= "\t\t.progressive-image-container {\n";

            // For width
            $content .= "\t\t\twidth: ".$this->generateVariableFallbackForBreakpoint($name, $layouts, 'width').";\n";
            // For aspect
            $content .= "\t\t\taspect-ratio: ".$this->generateVariableFallbackForBreakpoint($name, $layouts, 'aspect').";\n";

            $content .= "\t\t}\n";
            $content .= "\t}\n\n";
        }
		$content .= "}\n\n";

        return $content;
    }

    private function generateVariableFallback(array $sortedLayouts, string $type): string
    {
        $variables = [];
        foreach ($sortedLayouts as $name => $layout) {
            $variables[] = sprintf('var(--img-%s-%s', $type, $name);
        }

        $result = implode(",\n\t\t\t", $variables);
        if (count($variables) > 1) {
            $result .= str_repeat(')', count($variables));
        } else {
            $result .= ')';
        }

        return $result;
    }

    private function generateVariableFallbackForBreakpoint(string $currentBreakpoint, array $allLayouts, string $type): string
    {
        // Get all layouts with min_viewport <= current min_viewport, sorted descending
        $currentMinViewport = $allLayouts[$currentBreakpoint]['min_viewport'];
        $filteredLayouts = array_filter($allLayouts, fn ($l) => $l['min_viewport'] <= $currentMinViewport);
        uasort($filteredLayouts, fn ($a, $b) => $b['min_viewport'] <=> $a['min_viewport']);

        $variables = [];
        foreach ($filteredLayouts as $name => $layout) {
            $variables[] = sprintf('var(--img-%s-%s', $type, $name);
        }

        // Add legacy fallback for min_viewport 0
        foreach ($filteredLayouts as $name => $layout) {
            if (0 === $layout['min_viewport']) {
                $variables[] = sprintf('var(--img-%s', $type);
                break;
            }
        }

        $result = implode(', ', $variables);
        if (count($variables) > 1) {
            $result .= str_repeat(')', count($variables));
        } else {
            $result .= ')';
        }

        return $result;
    }
}
