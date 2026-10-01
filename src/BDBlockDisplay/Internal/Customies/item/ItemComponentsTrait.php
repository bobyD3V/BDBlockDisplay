<?php
declare(strict_types=1);

namespace BDBlockDisplay\Internal\Customies\item;

use BDBlockDisplay\Internal\Customies\item\component\CanDestroyInCreativeComponent;
use BDBlockDisplay\Internal\Customies\item\component\DamageComponent;
use BDBlockDisplay\Internal\Customies\item\component\DisplayNameComponent;
use BDBlockDisplay\Internal\Customies\item\component\DurabilityComponent;
use BDBlockDisplay\Internal\Customies\item\component\FoodComponent;
use BDBlockDisplay\Internal\Customies\item\component\FuelComponent;
use BDBlockDisplay\Internal\Customies\item\component\HandEquippedComponent;
use BDBlockDisplay\Internal\Customies\item\component\IconComponent;
use BDBlockDisplay\Internal\Customies\item\component\ItemComponent;
use BDBlockDisplay\Internal\Customies\item\component\MaxStackSizeComponent;
use BDBlockDisplay\Internal\Customies\item\component\ProjectileComponent;
use BDBlockDisplay\Internal\Customies\item\component\ThrowableComponent;
use BDBlockDisplay\Internal\Customies\item\component\UseAnimationComponent;
use BDBlockDisplay\Internal\Customies\item\component\WearableComponent;
use pocketmine\entity\Consumable;
use pocketmine\inventory\ArmorInventory;
use pocketmine\item\Armor;
use pocketmine\item\Durable;
use pocketmine\item\Food;
use pocketmine\item\ProjectileItem;
use pocketmine\item\Sword;
use pocketmine\item\Tool;

trait ItemComponentsTrait {

	/** @var ItemComponent[] */
	private array $components;

	public function addComponent(ItemComponent $component): void {
		$this->components[$component->getName()] = $component;
	}

	public function hasComponent(string $name): bool {
		return isset($this->components[$name]);
	}

	/**
	 * @return ItemComponent[]
	 */
	public function getComponents(): array {
		return $this->components;
	}

	/**
	 * Initializes the item with default components that are required for the item to function correctly.
	 */
	protected function initComponent(string $texture): void {
		$this->addComponent(new IconComponent($texture));
		$this->addComponent(new CanDestroyInCreativeComponent());
		$this->addComponent(new MaxStackSizeComponent($this->getMaxStackSize()));

		if($this instanceof Armor) {
			$slot = match ($this->getArmorSlot()) {
				ArmorInventory::SLOT_HEAD => WearableComponent::SLOT_ARMOR_HEAD,
				ArmorInventory::SLOT_CHEST => WearableComponent::SLOT_ARMOR_CHEST,
				ArmorInventory::SLOT_LEGS => WearableComponent::SLOT_ARMOR_LEGS,
				ArmorInventory::SLOT_FEET => WearableComponent::SLOT_ARMOR_FEET,
				default => WearableComponent::SLOT_ARMOR
			};
			$this->addComponent(new WearableComponent($slot, $this->getDefensePoints()));
		}

		if($this instanceof Consumable) {
			if(($food = $this instanceof Food)) {
				$this->addComponent(new FoodComponent(!$this->requiresHunger()));
			}
			$this->addComponent(new UseAnimationComponent($food ? UseAnimationComponent::ANIMATION_EAT : UseAnimationComponent::ANIMATION_DRINK));
			$this->setUseDuration(20);
		}

		if($this instanceof Durable) {
			$this->addComponent(new DurabilityComponent($this->getMaxDurability()));
		}

		if($this instanceof ProjectileItem) {
			$this->addComponent(new ProjectileComponent(1.25, "projectile"));
			$this->addComponent(new ThrowableComponent(true));
		}

		if($this->getName() !== "Unknown") {
			$this->addComponent(new DisplayNameComponent($this->getName()));
		}

		if($this->getFuelTime() > 0) {
			$this->addComponent(new FuelComponent($this->getFuelTime()));
		}

		if($this->getAttackPoints() > 0) {
			$this->addComponent(new DamageComponent($this->getAttackPoints()));
		}

		if($this instanceof Tool) {
			$this->addComponent(new HandEquippedComponent());
			if ($this instanceof Sword) {
				$this->addComponent(new CanDestroyInCreativeComponent(false));
			}
		}
	}
}
