<?php

namespace DocParser\Command;

use DocParser\Parser;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Command\Command;

class SingleCommand extends Command {

    /**
     * @var InputInterface;
     */
    private $input;

    /**
     * @var OutputInterface;
     */
    private $output;

    protected function configure()
    {
        $this
            ->setName('parser:single')
            ->setDescription('Parse a single HTML file')
            ->addArgument('file', InputArgument::REQUIRED, 'Path to the file you want to process')
            ->addOption('examples', 'e', InputOption::VALUE_NONE, 'Include examples')
            ->addOption('output', 'o', InputOption::VALUE_OPTIONAL, 'Write the output to a file')
            ->addOption('pretty', 'p', InputOption::VALUE_NONE, 'Pretty print JSON output')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $file = $input->getArgument('file');
        $parseExamples = boolval($input->getOption('examples'));

        $outputFile = $input->getOption('output');
        $pretty = boolval($input->getOption('pretty'));

        if (!file_exists($file) || !is_readable($file)) {
            $output->writeln("<error>File \"${file}\" doesn't exist or is not readable.</error>");
            return 1;
        }

        $parser = new Parser();
        $parserResult = $parser->processFile($file, $parseExamples);

        $funcName = $parserResult->getFuncNames()[0];
        $result = $parserResult->getResult($funcName);

        if ($parseExamples) {
            $result['examples'] = $parserResult->getExamples($funcName);
        }

        $jsonData = json_encode($result, $pretty ? JSON_PRETTY_PRINT : 0);

        if ($outputFile) {
            file_put_contents($outputFile, $jsonData);
        } else {
            $output->write($jsonData);
        }
    }

}
