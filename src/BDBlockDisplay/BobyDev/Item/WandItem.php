<?php
declare(strict_types=1);

namespace BDBlockDisplay\BobyDev\Item;

use pocketmine\item\ItemIdentifier;

final class WandItem extends BDItem{public function __construct(ItemIdentifier $i,string $n='BDBlockDisplay Wand'){parent::__construct($i,$n);$this->boot('bd_wand_move');}
}
