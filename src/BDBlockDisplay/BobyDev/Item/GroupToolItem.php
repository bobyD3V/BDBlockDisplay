<?php
declare(strict_types=1);

namespace BDBlockDisplay\BobyDev\Item;

use pocketmine\item\ItemIdentifier;

final class GroupToolItem extends BDItem{public function __construct(ItemIdentifier $i,string $n='BDBlockDisplay Group Tool'){parent::__construct($i,$n);$this->boot('bd_group_tool');}
}
