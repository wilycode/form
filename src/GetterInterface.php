<?php

declare(strict_types=1);

namespace WilyCode\Form;

interface GetterInterface
{
    public function getField(string $name): Field;
    public function getFieldset(string $name): Fieldset;
    public function getCollection(string $name): Collection;
}