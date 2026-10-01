<?php

namespace EvolutionCMS {
    class Core extends \Illuminate\Container\Container
    {
        /** @var array<string, mixed> */
        public $documentObject = [];

        public function getConfig(string $name = '', mixed $default = null): mixed
        {
        }
    }
}
