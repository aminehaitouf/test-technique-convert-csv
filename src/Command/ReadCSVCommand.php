<?php

namespace App\Command;

use App\Entity\ReadCSV;
use DateTime;
use jc21\CliTable;
use jc21\CliTableManipulator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Serializer\Encoder\CsvEncoder;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

class ReadCSVCommand extends Command
{
    protected static $defaultName = 'read:csv';
    protected $decoder;

    public function __construct()
    {
        $encoders = [new JsonEncoder(), new CsvEncoder()];
        $normalizers = [new ObjectNormalizer()];
        $this->decoder = new Serializer($normalizers, $encoders);
        parent::__construct();
    }

    protected function configure()
    {
        $this->setDescription("Read csv")
            ->addOption('source', 's', InputOption::VALUE_REQUIRED)
            ->addOption(
                'json',
                null,
                InputOption::VALUE_OPTIONAL,
                'Json or not',
                false
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $source = $input->getOption("source");
        $json_check = $input->getOption("json") == "true";
        $data = $this->csv_to_entity($source);
        if ($json_check) {
            $res = $this->convertToArray($data);
            echo json_encode($res);
            return;
        }
        $this->display($data);
    }

    private function csv_to_entity($source)
    {
        $array = [];

        $context = [
            CsvEncoder::DELIMITER_KEY => ';',
            CsvEncoder::ENCLOSURE_KEY => '"',
            CsvEncoder::ESCAPE_CHAR_KEY => '\\',
            CsvEncoder::KEY_SEPARATOR_KEY => ',',
        ];


        $results = $this->decoder->decode(file_get_contents($source), 'csv', $context);
        foreach ($results as $result) {
            $date = DateTime::createFromFormat('Y-m-d H:i:s', $result["created_at"]);
            $slug = explode(" ", str_replace(",", "", strtolower($result["title"])));
            $slug = implode("-", $slug);

            $readCsv = new ReadCSV();
            $readCsv2 = new ReadCSV();

            $readCsv->setSku(intval($result["sku"]));
            $readCsv->setTitle($result["title"]);
            $readCsv->setIsEnabled(intval($result["is_enabled"]) == 0 ? "Disable" : "Enable" );
            $readCsv->setPrice(round(floatval($result["price"]), 1));
            $readCsv->setCurrency($result["currency"]);
//            echo str_replace("<br/>", "\n", $result["description"]);
            $readCsv->setCreatedAt($date->format("l, d-M-Y G:i:s T"));
            $readCsv->setSlug($slug);
            $price_curr = number_format($readCsv->getPrice(), 2, ',', ' ')."".$readCsv->getCurrency();
            $readCsv->setPriceCurr($price_curr);

            if (str_contains($result["description"], "<br/>")) {
                $desc = explode("<br/>", $result["description"])[0];
                $desc2 = explode("<br/>", $result["description"])[1];

                $readCsv->setDescription($desc);
                $readCsv2->setDescription($desc2);
            } elseif(str_contains($result["description"], "\\r")) {;
                $desc = explode("\\r", $result["description"])[0];
                $desc2 = explode("\\r", $result["description"])[1];

                $readCsv->setDescription($desc);
                $readCsv2->setDescription($desc2);
            } else {
                $readCsv->setDescription($result["description"]);
            }
            $array[] = $readCsv;
            $array[] = $readCsv2;
        }

        return $array;
    }

    private function convertToArray($data): array
    {
        $i = 0;
        foreach($data as $d) {
            $data[$i] = json_decode($this->decoder->serialize($d, 'json'), true);
            $i++;
        }
        return $data;
    }

    private function display($data) {
        $table = new CliTable;

        $res = $this->convertToArray($data);

        $table->setChars(array(
            'top'          => '-',
            'top-mid'      => '+',
            'top-left'     => '+',
            'top-right'    => '+',
            'bottom'       => '-',
            'bottom-mid'   => '+',
            'bottom-left'  => '+',
            'bottom-right' => '+',
            'left'         => '|',
            'left-mid'     => '+',
            'mid'          => '-',
            'mid-mid'      => '+',
            'right'        => '|',
            'right-mid'    => '+',
            'middle'       => '| ',
        ));

        $table->addField('Sku', 'sku', false);
        $table->addField('Status', 'isEnabled', false);
        $table->addField('Price', 'priceCurr', false);
        $table->addField('Description', 'description', false);
        $table->addField('Created At', 'createdAt', false);
        $table->addField('Slug', 'slug', false);
        $table->injectData($res);
        $table->display();
    }
}