<?php

namespace MHz\MysqlVector\Nlp;

use OnnxRuntime\Model;
use OnnxRuntime\Vendor;

class Embedder
{
    private Model $model;
    private BertTokenizer $tokenizer;

    const QUERY_INSTRUCTION = "Represent this sentence for searching relevant passages:";
    const EMBEDDING_DIMENSIONS = 384;
    const MAX_LENGTH = 512;

    public function __construct() {
        // check if onnxruntime is installed
        ob_start();
        try {
            Vendor::check();
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        ob_end_clean();

        // load model
        try {
            $this->model = new Model(__DIR__ . '/model_quantized.onnx');
        } catch (\Throwable $e) {
            throw $e;
        }

        // load tokenizer
        $config = json_decode(file_get_contents(__DIR__ . '/tokenizer.json'), true);
        $this->tokenizer = new BertTokenizer($config);
    }

    public function getInputs(): array {
        return $this->model->inputs();
    }

    public function getOutputs(): array {
        return $this->model->outputs();
    }

    /**
     * Returns the number of dimensions of the output vector.
     * @return int
     */
    public function getDimensions(): int {
        return $this->model->outputs()[0]['shape'][2];
    }

    /**
     * Calculates the embedding of a text.
     * @param array $text Batch of text to embed
     * @return array Batch of embeddings
     * @throws \Exception
     */
    public function embed(array $text, bool $prependQuery = false): array {

        if($prependQuery) {
            // Add query instruction to text
            $text = array_map(function($t) {
                return self::QUERY_INSTRUCTION . ' ' . $t;
            }, $text);
        }

        $tokens = $this->tokenizer->call($text, [
            'text_pair' => null,
            'add_special_tokens' => true,
            'padding' => true,
            'truncation' => true,
            'max_length' => null,
            'return_tensor' => false
        ]);

        $outputs = $this->model->predict($tokens, outputNames: ['last_hidden_state']);
        return $outputs['last_hidden_state'];
    }

    private function dotProduct(array $a, array $b): float {
        return \array_sum(\array_map(
            function ($a, $b) {
                return $a * $b;
            },
            $a,
            $b
        ));
    }

    private function l2Norm(array $a): float {
        return \sqrt(\array_sum(\array_map(function($x) { return $x * $x; }, $a)));
    }

    private function cosine(array $a, array $b): float {
        $dotproduct = $this->dotProduct($a, $b);
        $normA = $this->l2Norm($a);
        $normB = $this->l2Norm($b);
        return 1.0 - ($dotproduct / ($normA * $normB));
    }

    /**
     * Calculates the cosine similarity between two vectors.
     * @param array $a
     * @param array $b
     * @return float
     */
    public function getCosineSimilarity(array $a, array $b): float {
        return 1.0 - $this->cosine($a, $b);
    }

    public function getMaxLength(): int {
        return $this->tokenizer->modelMaxLength;
    }
}