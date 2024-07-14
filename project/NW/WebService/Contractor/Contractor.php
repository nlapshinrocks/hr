<?php
declare(strict_types=1);

namespace NW\WebService\Contractor;


use NW\WebService\Contractor\Seller\Seller;

/**
 * @property Seller $Seller
 */
class Contractor
{
    const int TYPE_CUSTOMER = 0;
    //TODO свойства стоит переделать в protected или private и реализовать getter/setter
    public int $id;
    public string $type;
    public string $name;

    public function __construct(int $resellerId)
    {
        //TODO нужно реализовать метод, раз он используется в getById и там зачем-то передается аргумент $resellerId
    }

    public static function getById(int $resellerId): self
    {
        return new self($resellerId); // fakes the getById method
    }

    public function getFullName(): string
    {
        return $this->name . ' ' . $this->id;
    }
}
