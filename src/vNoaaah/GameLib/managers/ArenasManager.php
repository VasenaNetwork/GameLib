<?php

/**
 *   .oooooo.                                          ooooo         o8o   .o8
 *  d8P'  `Y8b                                         `888'         `"'  "888
 * 888            .oooo.   ooo. .oo.  .oo.    .ooooo.   888         oooo   888oooo.
 * 888           `P  )88b  `888P"Y88bP"Y88b  d88' `88b  888         `888   d88' `88b
 * 888     ooooo  .oP"888   888   888   888  888ooo888  888          888   888   888
 * `88.    .88'  d8(  888   888   888   888  888    .o  888       o  888   888   888
 *  `Y8bood8P'   `Y888""8o o888o o888o o888o `Y8bod8P' o888ooooood8 o888o  `Y8bod8P'
 * 
 * @author vp817, Laith98Dev
 * 
 * Copyright (C) 2023  vp817
 * 
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace vNoaaah\GameLib\managers;

use Closure;
use vNoaaah\GameLib\arena\Arena;
use function is_null;
use function array_key_exists;

final class ArenasManager
{

	private array $loadedArenas = [];

	/**
	 * @param string $arenaID
	 * @param Arena $arena
	 * @param Closure $onSuccess
	 * @param Closure $onFail
	 * @return void
	 */
	public function signAsLoaded(string $arenaID, Arena $arena, ?Closure $onSuccess = null, ?Closure $onFail = null): void
	{
		if ($this->hasLoadedArena(arenaID: $arenaID)) {
			if (!is_null($onFail)) $onFail($arenaID);
			return;
		}

		$this->loadedArenas[$arenaID] = $arena;
		if (!is_null($onSuccess)) $onSuccess($arena);
	}

	/**
	 * @param string $arenaID
	 * @param Closure $onSuccess
	 * @param Closure $onFail
	 * @return void
	 */
	public function unsignFromBeingLoaded(string $arenaID, ?Closure $onSuccess = null, ?Closure $onFail = null): void
	{
		if (!$this->hasLoadedArena(arenaID: $arenaID)) {
			if (!is_null($onFail)) $onFail($arenaID);
			return;
		}

		unset($this->loadedArenas[$arenaID]);
		if (!is_null($onSuccess)) $onSuccess();
	}

	/**
	 * @param string $arenaID
	 * @param Closure $onSuccess
	 * @param Closure $onFail
	 * @return void
	 */
	public function getLoadedArena(string $arenaID, Closure $onSuccess, ?Closure $onFail = null): void
	{
		if (!$this->hasLoadedArena(arenaID: $arenaID)) {
			if (!is_null($onFail)) $onFail($arenaID);
			return;
		}

		$onSuccess($this->loadedArenas[$arenaID]);
	}

	/**
	 * @param string $arenaID
	 * @return bool
	 */
	public function hasLoadedArena(string $arenaID): bool
	{
		return array_key_exists($arenaID, $this->loadedArenas);
	}

	/**
	 * @return Arena[]
	 */
	public function getAll(): array
	{
		return $this->loadedArenas;
	}
}
