<?php
declare(strict_types=1);

namespace NW\WebService\References\Operations;

abstract class ReferencesOperation
{
    abstract public function doOperation(): array;

    public function getRequest($pName): array
    {
        //TODO по-хорошему нужно обрабатывать, если в request[] не массив, как ожидается.
        return is_array($_REQUEST[$pName]) ? $_REQUEST[$pName] : [];
    }
}
