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
     *     columns?: int,
     *     gutter?: int
     * } $gridConfig The full responsive_strategy.grid config array. `columns`/`gutter`
     *               are accepted here because they are part of that shared config node,
     *               but this command doesn't need them: it only emits `var()` fallback
     *               chains, not pixel widths. `columns` is consumed by
     *               ResponsiveAttributeGenerator, which does the per-breakpoint pixel math.
     */
    public function __construct(
        private readonly array $gridConfig,
        private readonly string $projectDir,
        private readonly Filesystem $filesystem = new Filesystem(),
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

        if (str_contains($path, '..')) {
            $io->error(sprintf('The path "%s" must not contain ".." segments.', $path));

            return Command::FAILURE;
        }

        if (!str_starts_with($path, '/')) {
            $path = $this->projectDir.'/'.$path;
        }

        if (!$this->filesystem->exists($path)) {
            $this->filesystem->mkdir($path);
        }

        $filePath = $path.'/progressive-image-custom.css';

        $cssContent = $this->generateCssContent();

        $this->filesystem->dumpFile($filePath, $cssContent);

        $io->success(sprintf('CSS file generated at %s', $filePath));

        return Command::SUCCESS;
    }

    private function generateCssContent(): string
    {
        $layouts = $this->gridConfig['layouts'];

        // Sort layouts by min_viewport ascending to easily find the next breakpoint
        $mediaLayouts = $layouts;
        uksort($mediaLayouts, function ($a, $b) use ($mediaLayouts) {
            if ($mediaLayouts[$a]['min_viewport'] === $mediaLayouts[$b]['min_viewport']) {
                return 0;
            }

            return $mediaLayouts[$a]['min_viewport'] < $mediaLayouts[$b]['min_viewport'] ? -1 : 1;
        });

        $content = "/* Progressive Image Container - Custom Breakpoints */\n";
        $content .= "@layer vendor {\n";
        $content .= "\t.progressive-image-container {\n";
        $content .= "\t\tdisplay: block;\n";

        // Sort layouts by min_viewport descending for the root .progressive-image-container nested variables
        $sortedLayouts = $layouts;
        uksort($sortedLayouts, function ($a, $b) use ($sortedLayouts) {
            if ($sortedLayouts[$a]['min_viewport'] === $sortedLayouts[$b]['min_viewport']) {
                return strcmp($b, $a);
            }

            return $sortedLayouts[$a]['min_viewport'] > $sortedLayouts[$b]['min_viewport'] ? -1 : 1;
        });

        // Root width variable with fallbacks
        $content .= "\t\twidth: ".$this->generateVariableFallback($sortedLayouts, 'width', true).";\n";

        // Root aspect-ratio variable with fallbacks
        $content .= "\t\taspect-ratio: ".$this->generateVariableFallback($sortedLayouts, 'aspect', true).";\n";

        $content .= "\t\tposition: relative;\n";
        $content .= "\t\toverflow: hidden;\n";
        $content .= "\t}\n\n";

        $layoutNames = array_keys($mediaLayouts);
        $i = 0;
        foreach ($mediaLayouts as $name => $layout) {
            $nextBreakpoint = isset($layoutNames[$i + 1]) ? $mediaLayouts[$layoutNames[$i + 1]] : null;

            if (0 === $layout['min_viewport'] && $nextBreakpoint) {
                $content .= sprintf("/* %s: %dpx */\n", $name, $layout['min_viewport']);
                $content .= sprintf("@media (max-width: %dpx) {\n", $nextBreakpoint['min_viewport']);
                $content .= "\t\t.progressive-image-container {\n";
                $content .= sprintf("\t\t\twidth: %s;\n", $this->generateVariableFallbackForBreakpoint($name, $layouts, 'width'));
                $content .= sprintf("\t\t\taspect-ratio: %s;\n", $this->generateVariableFallbackForBreakpoint($name, $layouts, 'aspect'));
                if (null !== $layout['max_container']) {
                    $content .= sprintf("\t\t\tmax-width: %dpx;\n", $layout['max_container']);
                }
                $content .= "\t\t}\n";
                $content .= "\t}\n\n";

                ++$i;
                continue;
            }

            if (0 === $layout['min_viewport']) {
                ++$i;
                continue;
            }

            $content .= sprintf("/* %s: %dpx */\n", $name, $layout['min_viewport']);
            $content .= sprintf("@media (min-width: %dpx) {\n", $layout['min_viewport']);
            $content .= "\t\t.progressive-image-container {\n";

            // For width
            $content .= "\t\t\twidth: ".$this->generateVariableFallbackForBreakpoint($name, $layouts, 'width').";\n";
            // For aspect
            $content .= "\t\t\taspect-ratio: ".$this->generateVariableFallbackForBreakpoint($name, $layouts, 'aspect').";\n";
            if (null !== $layout['max_container']) {
                $content .= sprintf("\t\t\tmax-width: %dpx;\n", $layout['max_container']);
            }

            $content .= "\t\t}\n";
            $content .= "\t}\n\n";

            ++$i;
        }
        $content .= "}\n\n";

        return $content;
    }

    /**
     * @param array<string, array{min_viewport: int, max_container: int|null}> $sortedLayouts
     */
    private function generateVariableFallback(array $sortedLayouts, string $type, bool $includeGeneric = true): string
    {
        return $this->buildVariableFallbackChain(array_keys($sortedLayouts), $type, $includeGeneric, ",\n\t\t\t");
    }

    /**
     * @param array<string, array{min_viewport: int, max_container: int|null}> $allLayouts
     */
    private function generateVariableFallbackForBreakpoint(string $currentBreakpoint, array $allLayouts, string $type): string
    {
        // Get all layouts with min_viewport <= current min_viewport, sorted descending
        $currentMinViewport = $allLayouts[$currentBreakpoint]['min_viewport'];
        $filteredLayouts = array_filter($allLayouts, fn ($l) => $l['min_viewport'] <= $currentMinViewport);
        uksort($filteredLayouts, function ($a, $b) use ($filteredLayouts) {
            if ($filteredLayouts[$a]['min_viewport'] === $filteredLayouts[$b]['min_viewport']) {
                return strcmp($b, $a);
            }

            return $filteredLayouts[$a]['min_viewport'] > $filteredLayouts[$b]['min_viewport'] ? -1 : 1;
        });

        return $this->buildVariableFallbackChain(array_keys($filteredLayouts), $type, true, ', ');
    }

    /**
     * Shared by generateVariableFallback() and generateVariableFallbackForBreakpoint()
     * so a future formatting fix only has to be made in one place.
     *
     * @param array<int, string> $layoutNames
     */
    private function buildVariableFallbackChain(array $layoutNames, string $type, bool $includeGeneric, string $separator): string
    {
        $variables = [];
        foreach ($layoutNames as $name) {
            $variables[] = sprintf('var(--img-%s-%s', $type, $name);
        }

        if ($includeGeneric) {
            $variables[] = sprintf('var(--img-%s', $type);
        }

        $result = implode($separator, $variables);
        if (count($variables) > 1) {
            $result .= str_repeat(')', count($variables));
        } else {
            $result .= ')';
        }

        return $result;
    }
}
