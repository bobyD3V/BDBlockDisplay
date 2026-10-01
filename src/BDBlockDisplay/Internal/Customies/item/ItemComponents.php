<?php
declare(strict_types=1);

namespace BDBlockDisplay\Internal\Customies\item;

use BDBlockDisplay\Internal\Customies\item\component\ItemComponent;

interface ItemComponents {

	/**
	 * Add component adds a component to the item that can be returned in the getComponents() method to be sent over
	 * the network.
	 * @param ItemComponent $component
	 * @return void
	 */
	public function addComponent(ItemComponent $component): void;

	/**
	 * Returns if the item has the component with the provided name.
	 * @param string $name
	 * @return bool
	 */
	public function hasComponent(string $name): bool;

	/**
	 * Returns the fully-structured CompoundTag that can be sent to a client in the ItemComponentsPacket.
	 * @return ItemComponent[]
	 */
	public function getComponents(): array;
}
