<?php
declare(strict_types=1);
namespace BDBlockDisplay\BobyDev\Internal\Customies\Item\Component;
interface ItemComponent{
    public function getName():string;
    public function getValue():mixed;
    public function isProperty():bool;
}
