<?php

namespace DocParser\Command;

use DocParser\Availability;
use DocParser\Package;
use DocParser\Parser;
use DocParser\Utils;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;

class RunCommand extends ParserCommand {

    /**
     * @var Availability
     */
    private $avail;

    /**
     * @var array List of all available languages
     */
    private $allLanguages;

    /**
     * @var QuestionHelper
     */
    private $helper;

    /**
     * @var InputInterface;
     */
    private $input;

    /**
     * @var OutputInterface;
     */
    private $output;


    protected function configure() {
        $this
            ->setName('parser:run')
            ->setDescription('Run interactive parser console')
            ->addOption('language', 'l', InputOption::VALUE_REQUIRED, 'You know you want this specific language')
            ->addOption('mirror', 'm', InputOption::VALUE_REQUIRED, 'Set PHP documentation mirror')
            ->addOption('examples', 'e', InputOption::VALUE_REQUIRED, 's = skip, i = include, e = export')
            ->addOption('out-dir', 'o', InputOption::VALUE_REQUIRED, 'Output directory for parsed JSON manual', './output')
            ->addOption('tmp-dir', 't', InputOption::VALUE_REQUIRED, 'Directory for temporary files', sys_get_temp_dir())
            ->addOption('pretty', 'p', InputOption::VALUE_NONE, 'Pretty print JSON output')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        $this->avail = new Availability();
        $this->output = $output;
        $this->input = $input;
        $this->helper = $this->getHelper('question');

        $this->checkMemoryLimit();

        // Download list of all available languages and let user choose what they want.
        $this->output->writeln('Downloading ' . Availability::DOWNLOADS_URL);
        $this->allLanguages = $this->avail->listPackages();
        $languages = $this->input->getOption('language') ? [$this->input->getOption('language')] : $this->chooseLanguages();

        // Choose mirror.
        $mirror = $this->input->getOption('mirror') ?: $this->chooseMirror();

        // Choose what to do with function examples.
        $includeExamples = $this->stringExamplesParamsToConst($this->input->getOption('examples') ?: $this->chooseIncludeExamples());

        if (in_array('all', $languages)) {
            $languages = array_map(function($code) { return $code; }, array_keys($this->allLanguages));
        }

        $langTitles = array_map(function($code) { return $this->allLanguages[$code]; }, $languages);
        $this->output->writeln('Selected language[s]: ' . implode(', ', array_map(function($lang) { return '<info>' . $lang . '</info>'; }, $langTitles)) . " from <info>${mirror}</info> mirror site.\n");
        $this->output->writeln('');

        // Create output directory.
        $outDir = $this->input->getOption('out-dir');
        @mkdir($outDir, 0777, true);
        if (!is_dir($outDir)) {
            $this->output->writeln("<error>Unable to create \"${outDir}\" directory</error>");
            return 1;
        } elseif (!is_writable($outDir)) {
            $this->output->writeln("<error>Directory \"${outDir}\" directory isn't writable.</error>");
            return 1;
        }

        // Download, unpack and parse this language.
        $mirror = preg_replace('/^(http:\/\/|https:\/\/)/', '', $mirror);
        foreach ($languages as $code) {
            $package = new Package($code, $mirror);

            $this->downloadPackage($package);
            $this->unpackPackage($package);

            $this->parse($package, $outDir, $includeExamples);

            $package->cleanup();
        }
    }

    private function chooseLanguages() {
        $default = 'en';
        $choices = array_merge(['all' => 'All'], $this->allLanguages);
        $msg = "Choose languages you want to download.\nUse comma to separate multiple languages (default: ${default}):";
        $question = new ChoiceQuestion($msg, $choices, $default);
        $question->setAutocompleterValues(null);

        $question->setValidator(function($selected) use ($choices) {
            $selected = array_map(function($val) {
                return trim($val);
            }, explode(',', $selected));

            foreach ($selected as $selectedCode) {
                if (!isset($choices[$selectedCode])) {
                    throw new \RuntimeException("This isn't a valid value.");
                }
            }
            return $selected;
        });
        return $this->helper->ask($this->input, $this->output, $question);
    }

    private function chooseMirror() {
        $default = 'php.net';
        $question = new Question("Choose mirror site (default: ${default}): ", $default);
        return $this->helper->ask($this->input, $this->output, $question);
    }

    private function chooseIncludeExamples() {
        $default = 'i';
        $choices = [
            'i' => 'Include examples',
            'e' => 'Export to a separate file',
            's' => 'Skip examples'
        ];

        $question = new ChoiceQuestion("Do you want to include code examples (default: ${default})?", $choices, $default);
        $question->setMultiselect(false);
        $question->setAutocompleterValues(null);
        $question->setValidator(function($val) { return $val; });

        return $this->helper->ask($this->input, $this->output, $question);
    }

    private function downloadPackage(Package $package) {
        $url = $package->getUrl();

        preg_match('/\/(php_manual.*)\//U', $url, $matches);
        $filePath = $this->getTmpDir() . DIRECTORY_SEPARATOR . $matches[1];
        @mkdir(dirname($filePath), 0777, true);

        $bar = $this->createProgressBar();
        $bar->setMessage('Downloading <comment>' . $package->getUrl() . '</comment> to <comment>' . $filePath . '</comment>');
        $bar->start();

        $package->download($filePath, function($r, $downloadSize, $downloaded) use ($bar) {
            if ($downloadSize > 0) {
                $bar->setProgress(round(($downloaded / $downloadSize) * 100));
            }
        });

        $bar->finish();
        $this->output->writeln('');

        if (0 == filesize($filePath)) {
            $this->output->writeln('<error>Download failed. Please, check selected mirror site.</error>');
        }
    }

    public function unpackPackage(Package $package) {
        $this->output->writeln('Unpacking <comment>' . $package->getOrigFilename() . '</comment> ...');
        $manualDir = $package->unpack();
        $this->output->writeln('Unpacked to <comment>' . $manualDir . "</comment>\n");
        return $manualDir;
    }

    private function parse(Package $package, $outDir, $includeExamples) {
        $parser = new Parser();

        $bar = $this->createProgressBar(count($parser->getFilesToProcess($package->getUnpackedDir())));
        $bar->setRedrawFrequency(100);
        $bar->start();

        $results = $parser->processDir($package->getUnpackedDir(), $includeExamples, function($filename, $total, $processed) use ($bar) {
            $bar->setMessage('Processing: ' . $filename);
            $bar->advance();
        });

        $bar->finish();

        $functions = [];
        $examples = [];

        foreach ($results->getFuncNames() as $funcName) {
            $arrayRes = $results->getResult($funcName);
            $examplesRes = $results->getExamples($funcName);

            if ($examplesRes && is_array($arrayRes)) {
                if ($includeExamples == Parser::INCLUDE_EXAMPLES) {
                    $arrayRes = array_merge($arrayRes, [
                        'examples' => $examplesRes
                    ]);
                } elseif ($includeExamples == Parser::EXPORT_EXAMPLES) {
                    $examples[$funcName] = $examplesRes;
                }
            }
            $functions[$funcName] = $arrayRes;
        }

        $this->output->writeln('');
        $this->output->writeln("Warnings: <comment>" . $results->countAllWarnings() . "</comment> (malformed UTF8 in examples)");
        $this->output->writeln("Skipped files: <comment>" . count($results->getSkipped()) . "</comment> (no function definition found)");

        $basePath = $outDir . DIRECTORY_SEPARATOR . $package->getLang() . '_' . str_replace('.', '_', $package->getMirror());
        $this->saveOutput($basePath, $functions);
        $this->output->writeln("Total functions: <info>" . count($functions) . "</info>");

        $this->saveFunctionsList($basePath, $functions);

        if ($includeExamples == Parser::EXPORT_EXAMPLES) {
            $this->saveExamples($basePath, $examples);
            $this->output->writeln("Total examples: <info>" . $results->countAllExamples() . "</info>");
        }

        if ($this->output->isVerbose()) {
            foreach ($results->getWarnings() as $funcName => $warnings) {
                $this->output->writeln("<comment>Warning in ${funcName}</comment>");
                foreach ($warnings as $warning) {
                    $this->output->writeln($warning);
                }
            }
        }
        if ($this->output->isVeryVerbose()) {
            foreach (array_keys($results->getSkipped()) as $filename) {
                $this->output->writeln("<comment>Skipped ${filename}</comment>");
            }
        }
    }

    private function saveOutput($basePath, $functions) {
        $json = json_encode($functions, $this->getJsonEncoderFlags());
        $filePath = $basePath . '.json';
        file_put_contents($filePath, $json);

        $this->output->writeln("Saving JSON documentation to <info>${filePath}</info>");
        $this->printJsonError();
    }

    private function saveExamples($basePath, $examples) {
        $json = json_encode($examples, $this->getJsonEncoderFlags());
        $filePath = $basePath . '.examples.json';
        file_put_contents($filePath, $json);

        $this->output->writeln("Saving JSON with examples to <info>${filePath}</info>");
        $this->printJsonError();
    }

    private function saveFunctionsList($basePath, $functions) {
        $normalized = array_map(function($name) {
            return strtolower($name);
        }, array_keys($functions));

        $json = json_encode($normalized, $this->getJsonEncoderFlags());
        $filePath = $basePath . '.list.json';
        file_put_contents($filePath, $json);
        $this->output->writeln("Saving list of all functions to <info>${filePath}</info>");
        $this->printJsonError();
    }

    private function getTmpDir() {
        return ($this->input->getOption('tmp-dir') ?: sys_get_temp_dir());
    }

    private function printJsonError() {
        if (json_last_error()) {
            $this->output->writeln('<error>' . json_last_error_msg() . '</error>');
        }
    }

    private function checkMemoryLimit() {
        $memLimit = ini_get('memory_limit');
        $minimumRequired = 128;
        $bytes = Utils::convertSize(ini_get('memory_limit'));
        if ($bytes < $minimumRequired * pow(1024, 2)) {
            $this->output->writeln("<comment>Memory limit ${memLimit} might be too low. Trying to set ${minimumRequired}M.</comment>");
            ini_set('memory_limit', $minimumRequired . 'M');
        }
    }

    private function createProgressBar($max = 100) {
        $bar = new ProgressBar($this->output, $max);
        $bar->setBarCharacter('<info>#</info>');
        $bar->setProgressCharacter('<info>#</info>');
        $bar->setFormat("%message%\n" . '%percent:3s%% [%bar%] %elapsed:6s%/%estimated:-6s%   ');
        return $bar;
    }

    private function getJsonEncoderFlags() {
        return ($this->input->getOption('pretty') ? JSON_PRETTY_PRINT : 0) | JSON_NUMERIC_CHECK | JSON_UNESCAPED_UNICODE;
    }

}