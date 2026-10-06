<?php
declare(strict_types=1);
namespace BDBlockDisplay\BobyDev\Internal\Customies\Item\Component;
final class InteractButtonComponent implements ItemComponent{
    public function __construct(private string $label){}
    public function getName():string{return 'minecraft:interact_button';}
    public function getValue():string{return $this->label;}
    public function isProperty():bool{return false;}
}
