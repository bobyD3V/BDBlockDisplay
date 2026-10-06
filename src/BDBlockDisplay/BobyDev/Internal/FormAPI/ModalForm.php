<?php
declare(strict_types=1);
namespace BDBlockDisplay\BobyDev\Internal\FormAPI;
use pocketmine\form\Form;
use pocketmine\player\Player;
class ModalForm implements Form{
    public function __construct(private string $json,private \Closure $handler){}
    public function jsonSerialize():mixed{return json_decode($this->json,true,512,JSON_THROW_ON_ERROR);}
    public function handleResponse(Player $player,mixed $data):void{($this->handler)($player,$data);}
}
