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

namespace vNoaaah\GameLib\arena\modes;

use Closure;
use pocketmine\player\Player;
use vNoaaah\GameLib\arena\Arena;
use vNoaaah\GameLib\arena\modes\ArenaMode;
use vNoaaah\GameLib\arena\states\ArenaStates;
use vNoaaah\GameLib\event\ArenaTeleportEvent;
use vNoaaah\GameLib\event\enums\ArenaTeleportCause;
use vNoaaah\GameLib\event\PlayerJoinArenaEvent;
use vNoaaah\GameLib\event\PlayerQuitArenaEvent;
use vNoaaah\GameLib\exceptions\GameLibInvalidArgumentException;
use vNoaaah\GameLib\GameLib;
use vNoaaah\GameLib\managers\TeamManager;
use vNoaaah\GameLib\player\ArenaPlayer;
use vNoaaah\GameLib\utils\Team;
use vNoaaah\GameLib\utils\Utils;
use function intval;
use function is_array;
use function is_null;
use function is_object;
use function strtolower;

abstract class TeamModeAbstract extends ArenaMode
{

	private TeamManager $teamManager;
	private GameLib $gamelib;

	/**
	 * @param mixed ...$arguments
	 * @return void
	 * @throws GameLibInvalidArgumentException
	 */
	public function init(mixed ...$arguments): void
	{
		$teams = $arguments[0] ?? null;
		$arena = $arguments[1] ?? null;
		$gamelib = $arguments[2] ?? null;

		if (!is_array($teams)) {
			throw new GameLibInvalidArgumentException(message: "The teams is invalid");
		}

		if (!is_object($arena)) {
			throw new GameLibInvalidArgumentException(message: "The arena is not an object");
		}

		if (!is_object($gamelib)) {
			throw new GameLibInvalidArgumentException(message: "The gamelib is not an object");
		}

		if (!$arena instanceof Arena) {
			throw new GameLibInvalidArgumentException(message: "The arena is invalid");
		}

		if (!$gamelib instanceof GameLib) {
			throw new GameLibInvalidArgumentException(message: "The gamelib is invalid");
		}

		$this->teamManager = new TeamManager(arena: $arena);
		foreach ($teams as $teamName) {
			$team = Team::fromString(value: $teamName);
			if (is_null($team)) {
				throw new GameLibInvalidArgumentException(message: "The arena teams is invalid");
			}
			$this->teamManager->addTeam(team: $team);
		}

		$this->gamelib = $gamelib;
	}

	/**
	 * @param string $bytes
	 * @return bool
	 */
	public function hasPlayer(string $bytes): bool
	{
		return $this->teamManager->isPlayerInATeamFromBytes(bytes: $bytes);
	}

	/**
	 * @return ArenaPlayer[]
	 */
	public function getPlayers(): array
	{
		return $this->teamManager->getTeamsPlayers();
	}

	/**
	 * @return int
	 */
	public function getMaxPlayers(): int
	{
		return intval(count($this->teamManager->getTeams()) * $this->getMaxPlayersPerTeam());
	}

	/**
	 * @param string $bytes
	 * @param null|Closure $onSuccess
	 * @param null|Closure $onFail
	 * @return void
	 */
	public function removePlayer(string $bytes, ?Closure $onSuccess = null, ?Closure $onFail = null): void
	{
		$this->teamManager->getTeamOfPlayerFromBytes(
			bytes: $bytes,
			onSuccess: fn (Team $team) => $team->removePlayer(
				bytes: $bytes,
				onSuccess: fn () => !is_null($onSuccess) ? $onSuccess() : null
			),
			onFail: $onFail
		);
	}

	/**
	 * @param Arena $arena
	 * @param Player $player
	 * @param null|Closure $onSuccess
	 * @param null|Closure $onFail
	 * @return void
	 */
	public function onJoin(Arena $arena, Player $player, ?Closure $onSuccess = null, ?Closure $onFail = null): void
	{
		$bytes = $player->getUniqueId()->getBytes();
		$arenaMessages = $arena->getMessages();

		if ($this->hasPlayer($bytes)) {
			if (!is_null($onFail)) $onFail($arenaMessages->PlayerAlreadyInsideAnArena());
			return;
		}
		if ($this->getPlayerCount() > $this->getMaxPlayers()) {
			if (!is_null($onFail)) $onFail($arenaMessages->ArenaIsFull());
			return;
		}
		if ($arena->getState()->equals(ArenaStates::INGAME())) {
			if (!is_null($onFail)) $onFail($arenaMessages->ArenaIsAlreadyRunning());
			return;
		}

		$this->teamManager->addPlayerToRandomTeam(
			player: $player,
			onSuccess: function (ArenaPlayer $player, Team $team) use ($arena, $arenaMessages, $onSuccess): void {
				$event = new PlayerJoinArenaEvent(
					player: $player,
					arena: $arena
				);
				$event->call();

				$arenaPlayer = $event->getPlayer();
				$eventArena = $event->getArena();
				$cells = $arenaPlayer->getCells();

				$arenaPlayer->setTeam(team: $team);
				$arenaPlayer->setAll();

				$tpEvent = new ArenaTeleportEvent(
					player: $arenaPlayer,
					arena: $eventArena,
					cause: ArenaTeleportCause::LOBBY(),
					location: $arena->getLobbySettings()->getLocation()
				);
				$tpEvent->call();

				$cells->teleport(pos: $tpEvent->getLocation());

				$arena->getMessageBroadcaster()->broadcastMessage(
					Utils::replaceMessageContent(
						replacement: [
							"%name%" => $arenaPlayer->getDisplayName(),
							"%current%" => $this->getPlayerCount(),
							"%max%" => $this->getMaxPlayers()
						],
						message: $arenaMessages->SucessfullyJoinedArena()
					)
				);

				if (!is_null($onSuccess)) $onSuccess();
			}
		);
	}

	/**
	 * @param Arena $arena
	 * @param Player $player
	 * @param null|Closure $onSuccess
	 * @param null|Closure $onFail
	 * @param bool $notifyPlayers
	 * @param bool $force
	 * @return void
	 */
	public function onQuit(Arena $arena, Player $player, ?Closure $onSuccess = null, ?Closure $onFail = null, bool $notifyPlayers = true, bool $force = false): void
	{
		$bytes = $player->getUniqueId()->getBytes();
		$arenaMessages = $arena->getMessages();

		if (!$this->hasPlayer($bytes)) {
			if (!is_null($onFail)) $onFail($arenaMessages->NotInsideAnArenaToLeave());
			return;
		}

		if (!$force && ($arena->getState()->equals(ArenaStates::INGAME()) || $arena->getState()->equals(ArenaStates::RESTARTING()))) {
			if (!is_null($onFail)) $onFail($arenaMessages->CantLeaveDueToState());
			return;
		}

		$this->teamManager->getTeamOfPlayerFromBytes(
			bytes: $bytes,
			onSuccess: function (Team $team) use ($arena, $arenaMessages, $bytes, $onSuccess, $notifyPlayers): void {
				$team->getPlayer(
					bytes: $bytes,
					onSuccess: function (ArenaPlayer $player) use ($team, $arena, $arenaMessages, $bytes, $onSuccess, $notifyPlayers): void {
						$event = new PlayerQuitArenaEvent(
							player: $player,
							arena: $arena
						);
						$event->call();

						$arenaPlayer = $event->getPlayer();
						$eventArena = $event->getArena();

						$arenaPlayer->setTeam(null);
						$arenaPlayer->setAll(true);

						$team->removePlayer($bytes, function () use ($arenaMessages, $arenaPlayer, $eventArena, $onSuccess, $notifyPlayers): void {
							$cells = $arenaPlayer->getCells();

							$notifyCB = fn () => $eventArena->getMessageBroadcaster()->broadcastMessage(
								Utils::replaceMessageContent(
									replacement: [
										"%name%" => $arenaPlayer->getDisplayName(),
										"%current%" => $this->getPlayerCount(),
										"%max%" => $this->getMaxPlayers()
									],
									message: $arenaMessages->SucessfullyLeftArena()
								)
							);

							$cells->teleport(pos: $this->gamelib->getWorldManager()->getDefaultWorld()->getSpawnLocation());
							if ($notifyPlayers) $notifyCB;
							if (!is_null($onSuccess)) $onSuccess();
						});
					}
				);
			}
		);
	}

	/**
	 * @param Arena $arena
	 * @param array $spawns
	 * @return void
	 */
	public function relocatePlayersToSpawns(Arena $arena, array $spawns): void
	{
		$players = $this->getPlayers();
		foreach ($players as $player) {
			$this->teamManager->getTeamOfPlayerFromBytes($player->getCells()->getUniqueId()->getBytes(), function (Team $team) use ($arena, $spawns, $player): void {
				$event = new ArenaTeleportEvent(
					player: $player,
					arena: $arena,
					cause: ArenaTeleportCause::SPAWN(),
					location: $arena->getLocationOfSpawn(
						spawn: $spawns[strtolower($team->getName())]
					)
				);
				$event->call();

				$eventPlayerCells = $event->getPlayer()->getCells();
				$eventLocation = $event->getLocation();

				$eventPlayerCells->teleport(pos: $eventLocation);
			});
		}
	}
}
