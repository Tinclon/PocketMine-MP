<?php

/*
 *
 *  ____            _        _   __  __ _                  __  __ ____
 * |  _ \ ___   ___| | _____| |_|  \/  (_)_ __   ___      |  \/  |  _ \
 * | |_) / _ \ / __| |/ / _ \ __| |\/| | | '_ \ / _ \_____| |\/| | |_) |
 * |  __/ (_) | (__|   <  __/ |_| |  | | | | | |  __/_____| |  | |  __/
 * |_|   \___/ \___|_|\_\___|\__|_|  |_|_|_| |_|\___|     |_|  |_|_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author bcyorkiepocketmine@gmail.com
 * @link https://forums.pocketmine.net/plugins/drawstuff.410/
 *
 *
*/

namespace pocketmine\command\defaults;

use pocketmine\item\Item;
use pocketmine\item\ItemBlock;
use pocketmine\block\Block;
use pocketmine\command\CommandSender;
use pocketmine\Player;
use pocketmine\math\Vector3;
use pocketmine\utils\Config;

class DrawCommand extends VanillaCommand{

    const DIR_NORTH = '0';      // When we're facing north, positive x value recedes into the distance, positive z value goes to the right
    const DIR_EAST = '1';       // When we're facing east,  positive z value recedes into the distance, negative x value goes to the right
    const DIR_SOUTH = '2';      // When we're facing south, negative x value recedes into the distance, negative z value goes to the right
    const DIR_WEST = '3';       // When we're facing west,  negative z value recedes into the distance, positive x value goes to the right


	public function __construct($name, $server){
		parent::__construct(
			$name,
			"Place blocks in various shapes",   // Description
			"/draw help"                        //Usage
		);
		$this->setPermission("pocketmine.command.draw");

        $this->server = $server;

		$this->arrRollback = array();
		$this->arrLastCommand = array();
		$this->arrSavedMacros = array();
		$this->blnSavingMacro = 0;
		$this->blnPlaying = 0;
		$this->objStartingVector = array();
		$this->objStartingDirection = '';

		//Return messages
		$this->arrReturnMessage['error_drawFromCommandLine'] = 'Can not run draw from command line.';
		$this->arrReturnMessage['error_noUsername'] = 'Enter a logged in username to get info.';
		$this->arrReturnMessage['error_duplicateRecording'] = 'This recording already exists, enter another name.';
		$this->arrReturnMessage['error_missingRecodingName'] = 'You must enter a name for saving.';
		$this->arrReturnMessage['error_noRecording'] = 'There is no recording with this name.';
		$this->arrReturnMessage['recording_saved'] = 'Recording has been saved.';
		$this->arrReturnMessage['recording_deleted'] = 'The recording has been removed.';
		$this->arrReturnMessage['recording_started'] = 'Recording started.';
		$this->arrReturnMessage['recording_cancelled'] = 'Recording cancelled.';
		$this->arrReturnMessage['recording_list'] = 'Available macros: ';
		$this->arrReturnMessage['undo'] = 'Last command has been undone.';
		$this->arrReturnMessage['floor'] = 'Nice looking floor you got there!';
		$this->arrReturnMessage['groundcover'] = 'A blanket of blocks!';
		$this->arrReturnMessage['pool'] = 'Time to cool off!';
		$this->arrReturnMessage['lavalake'] = 'Warm and invitine!';
		$this->arrReturnMessage['cut'] = 'Blocks have been removed.';
		$this->arrReturnMessage['replace'] = 'Blocks have been replaced.';
		$this->arrReturnMessage['steps'] = 'Stairway to heaven.';
		$this->arrReturnMessage['diamond'] = 'Diamonds are a girl\'s best friend.';
		$this->arrReturnMessage['sphere'] = 'The circle of life.';
		$this->arrReturnMessage['bubble'] = 'In a bubble of happiness.';
		$this->arrReturnMessage['volcano'] = 'Deep within the earth fire.';
		$this->arrReturnMessage['set_defaults'] = 'Defaults have been updated.';
		$this->arrReturnMessage['error_defaults'] = 'No defaults to update.';
		$this->arrReturnMessage['prism'] = 'Run for your life, it\'s a Cuboid!.';
		$this->arrReturnMessage['cube'] = 'Solid as could be!';
		$this->arrReturnMessage['pyramid'] = 'A true wonder!';
		$this->arrReturnMessage['tomb'] = 'A place to sleep!';
		$this->arrReturnMessage['wall'] = 'There\'s a wall for you!';
		$this->arrReturnMessage['box'] = 'Have a box!';
		$this->arrReturnMessage['string'] = 'Here is a message for you.';
		$this->arrReturnMessage['play'] = 'Playa';
		$this->arrReturnMessage['copy'] = 'Template saved to clone factory';
		$this->arrReturnMessage['paste'] = 'Package arrived from cloning factory';

		$this->init();
	}

	public function execute(CommandSender $sender, $currentAlias, array $args){

        if(!$this->testPermission($sender)){
            return true;
        }

        if(!isset($args[0])) {
            $args[0] = 'help';
        }

        $sender->sendMessage($this->commandHandler($args[0], $args, $sender, $currentAlias));

        return true;
    }


	public function init()
	{
		$this->arrSavedMacros = new Config($this->server->getDataPath() . '../' . 'draw.macros.yml', Config::YAML, array());
		$this->arrDefaults = new Config($this->server->getDataPath() . '../' . 'draw.config.yml', Config::YAML, array());
	}

	public function commandHandler($strCmd, $arrParams, $objIssuer, $strAlias, $objStartingVector = array(),$objStartingDirection = '')
	{

		//if coming from the console, just leave, can't run anything past this point.
		if(!$objIssuer instanceof Player) return $this->arrReturnMessage['error_drawFromCommandLine'];

		//a little debug feature I kept in, I needed to see postion and direction from command line for testing.
		if($strCmd === "userinfo")
		{
        	$return = 'x: ' . $objIssuer->getLocation()->x . ' y: ' . $objIssuer->getLocation()->y . ' z: ' . $objIssuer->getLocation()->z . ' dir:' . $objIssuer->getDirection();
			$this->server->getLogger()->info($objIssuer->getName() . ' ' . $return);
			return $return;
        }

		//make sure the user has their defaults defined
		$this->__fncSetupUserDefaults($objIssuer);

		$strOutput = "";

		//store the original arrParams so can store them for a repeat
		$arrOriginalParams = $arrParams;

		if(!empty($objStartingVector))
		{
			$this->objStartingVector = $objStartingVector;
		}
		else
		{
			$this->objStartingVector = new Vector3($objIssuer->getLocation()->x, $objIssuer->getLocation()->y, $objIssuer->getLocation()->z);
		}

        $this->objStartingVector->x = floor($this->objStartingVector->x);
        $this->objStartingVector->y = floor($this->objStartingVector->y);
        $this->objStartingVector->z = floor($this->objStartingVector->z);

		if($objStartingDirection === '')
		{
			$this->objStartingDirection = $objIssuer->getDirection();
		}
		else
		{
			$this->objStartingDirection = (int) $objStartingDirection;
		}


        $strSubCmd = strtolower(array_shift($arrParams));

        $this->server->getLogger()->info($objIssuer->getName() . '(dir: ' . $objIssuer->getDirection() . ') \\' . $strAlias . ' ' . $strSubCmd . ' ' . implode(' ',$arrParams));

        if($strSubCmd == 'help')
        {
            if(isset($arrParams[0]))
            {
                return $this->__fncHelp($strAlias,$arrParams[0]);
            }
            else
            {
                return $this->__fncHelp($strAlias);
            }
        }
        elseif(isset($arrParams[0]) && $arrParams[0] == 'help')
        {
            return $this->__fncHelp($strAlias,$strSubCmd);
        }
        elseif($strSubCmd == 'repeat')
        {
            return $this->commandHandler($this->arrLastCommand['strCmd'],$this->arrLastCommand['arrParams'],$objIssuer,$this->arrLastCommand['strAlias']);
        }
        elseif($strSubCmd == 'record')
        {
            if ($arrParams[0] == 'save')
            {
                if (isset($arrParams[1]))
                {
                    if (!$this->arrSavedMacros->exists($arrParams[1]))
                    {
                        $strOutput = $this->arrReturnMessage['recording_saved'];

                        $this->arrSavedMacros->set($arrParams[1], json_encode($this->arrCurrentMacro[$objIssuer->getName()]));

                        $this->blnSavingMacro = 0;
                        $this->arrCurrentMacro[$objIssuer->getName()] = array();

                        $this->arrSavedMacros->save();

                        return $strOutput;
                    }
                    else
                    {
                        return $this->arrReturnMessage['error_duplicateRecording'];
                    }
                }
                else
                {
                    return $this->arrReturnMessage['error_missingRecodingName'];
                }
            }
            elseif ($arrParams[0] == 'delete')
            {
                if (isset($arrParams[1]) && $this->arrSavedMacros->exists($arrParams[1]))
                {
                    $this->arrSavedMacros->remove($arrParams[1]);
                    $this->arrSavedMacros->save();

                    return $this->arrReturnMessage['recording_deleted'];
                }
                else
                {
                    return $this->arrReturnMessage['error_noRecording'];
                }
            }
            elseif ($arrParams[0] == 'start')
            {
                $this->arrCurrentMacro[$objIssuer->getName()] = array();
                $this->blnSavingMacro = 1;
                return $this->arrReturnMessage['recording_started'];
            }
            elseif ($arrParams[0] == 'cancel')
            {
                $this->blnSavingMacro = 0;
                $this->arrCurrentMacro[$objIssuer->getName()] = array();
                return $this->arrReturnMessage['recording_cancelled'];
            }
            else
            {
                return $this->__fncHelp($strAlias,$strSubCmd);
            }
        }
        elseif($strSubCmd == 'play')
        {
            if ($this->arrSavedMacros->exists($arrParams[0]))
            {
                $this->arrLastCommand['strCmd'] = $strCmd;
                $this->arrLastCommand['arrParams'] = $arrOriginalParams;
                $this->arrLastCommand['strAlias'] = $strAlias;
                $this->arrLastCommand['objStartingVector'] = new Vector3($objIssuer->getLocation()->x, $objIssuer->getLocation()->y, $objIssuer->getLocation()->z);
                $this->arrLastCommand['intCurrentDirection'] = $objIssuer->getDirection();
                return $this->__fncPlay($arrParams, $objIssuer);
            }
            else
            {
                $strAvailableMacros = implode(", ",array_keys($this->arrSavedMacros->getAll()));
                return $this->arrReturnMessage['recording_list'] . $strAvailableMacros;
            }
        }

        //reset the rollback array with each call, unless it is undo
        if(!$this->blnPlaying && $strSubCmd != 'undo')
        {

            $this->arrRollback[$objIssuer->getName()] = array();
            $this->arrLastCommand['strCmd'] = $strCmd;
            $this->arrLastCommand['arrParams'] = $arrOriginalParams;
            $this->arrLastCommand['strAlias'] = $strAlias;
            $this->arrLastCommand['objStartingVector'] = new Vector3($objIssuer->getLocation()->x, $objIssuer->getLocation()->y, $objIssuer->getLocation()->z);
            $this->arrLastCommand['intCurrentDirection'] = $objIssuer->getDirection();
        }

        //setup shortcuts for the arrParams
        $arrShortCuts = array();
        $arrShortCuts['h'] = 'height';
        $arrShortCuts['t'] = 'text';
        $arrShortCuts['l'] = 'length';
        $arrShortCuts['d'] = 'depth';
        $arrShortCuts['s'] = 'size';
        $arrShortCuts['w'] = 'width';
        $arrShortCuts['e'] = 'elevation';
        $arrShortCuts['r'] = 'radius';
        $arrShortCuts['a'] = 'aroundme';
        $arrShortCuts['n'] = 'name';
        $arrShortCuts['ex'] = 'exclude';
        $arrShortCuts['in'] = 'include';

        //set the arrParams to named parameters
        $arrNamedParams = array();
        foreach($arrParams AS $currentParam)
        {
            $arrTemp = explode(':',$currentParam);

            if(count($arrTemp) == 2)
            {
                if(isset($arrShortCuts[$arrTemp[0]]))
                {
                    $arrNamedParams[$arrShortCuts[$arrTemp[0]]] = $arrTemp[1];
                }
                else
                {
                    $arrNamedParams[$arrTemp[0]] = $arrTemp[1];
                }
            }
            elseif (count($arrTemp) ==1 && isset($arrNamedParams['text']))
            {
                $arrNamedParams['text'] .= ' ' . $arrTemp[0];
            }
        }

        switch($strSubCmd)
        {
            case 'copy':
                $strOutput = $this->__fncCopy($arrNamedParams, $objIssuer);
            break;

            case 'paste':
                $strOutput = $this->__fncPaste($arrNamedParams, $objIssuer);
            break;

            case 'measure':
                $strOutput = $this->__fncMeasure($arrNamedParams, $objIssuer);
            break;

            case 'cube':
                $strOutput = $this->__fncDrawCube($arrNamedParams, $objIssuer);
            break;

            case 'floor':
                $strOutput = $this->__fncDrawFloor($arrNamedParams, $objIssuer);
            break;

            case 'groundreplace':
                $strOutput = $this->__fncDrawGroundReplace($arrNamedParams, $objIssuer);
            break;

            case 'groundcover':
                $strOutput = $this->__fncDrawGroundCover($arrNamedParams, $objIssuer);
            break;

            case 'wall':
                $strOutput = $this->__fncDrawWall($arrNamedParams, $objIssuer);
            break;

            case 'pool':
                $strOutput = $this->__fncDrawPool($arrNamedParams, $objIssuer);
            break;

            case 'lavalake':
                $strOutput = $this->__fncDrawLavaLake($arrNamedParams, $objIssuer);
            break;

            case 'pyramid':
                $strOutput = $this->__fncDrawPyramid($arrNamedParams, $objIssuer);
            break;

            case 'tomb':
                $strOutput = $this->__fncDrawTomb($arrNamedParams, $objIssuer);
            break;

            case 'box':
                $strOutput = $this->__fncDrawBox($arrNamedParams, $objIssuer);
            break;

            case 'write':
                $strOutput = $this->__fncDrawString($arrNamedParams, $objIssuer);
            break;

            case 'prism':
                $strOutput = $this->__fncDrawPrism($arrNamedParams, $objIssuer);
            break;

            case 'cut':
                $strOutput = $this->__fncCut($arrNamedParams, $objIssuer);
            break;

            case 'replace':
                $strOutput = $this->__fncReplace($arrNamedParams, $objIssuer);
            break;

            case 'steps':
                $strOutput = $this->__fncDrawSteps($arrNamedParams, $objIssuer);
            break;

            case 'sphere':
                $strOutput = $this->__fncDrawSphere($arrNamedParams, $objIssuer);
            break;

            case 'bubble':
                $strOutput = $this->__fncDrawBubble($arrNamedParams, $objIssuer);
            break;

            case 'volcano':
                $strOutput = $this->__fncDrawVolcano($arrNamedParams, $objIssuer);
            break;

            case 'diamond':
                $strOutput = $this->__fncDrawDiamond($arrNamedParams, $objIssuer);
            break;

            case 'set':
                $strOutput = $this->__fncSetDefaults($arrNamedParams, $objIssuer);
            break;

            case 'undo':
                $strOutput = $this->__fncUndo($arrNamedParams, $objIssuer);
            break;
        }

		//if we are in saving mode, need to register this call
		if($this->blnSavingMacro === 1)
		{
			$this->arrCurrentMacro[$objIssuer->getName()][] = $this->arrLastCommand;
		}

		return $strOutput;
	}

    private function __fncCopy($arrParams, $objIssuer)
	{
		$intLength = (isset($arrParams['length']) && is_numeric ($arrParams['length'])) ? (int) $arrParams['length'] : $this->arrDefaults->get($objIssuer->getName())['length'];
		$intWidth = (isset($arrParams['width']) && is_numeric ($arrParams['width'])) ? (int) $arrParams['width'] : $this->arrDefaults->get($objIssuer->getName())['width'];
		$intHeight = (isset($arrParams['height']) && is_numeric ($arrParams['height'])) ? (int) $arrParams['height'] : $this->arrDefaults->get($objIssuer->getName())['height'];
		$intElevation = (isset($arrParams['elevation']) && is_numeric ($arrParams['elevation'])) ? (int) $arrParams['elevation'] : $this->arrDefaults->get($objIssuer->getName())['elevation'];
		$exclude = (isset($arrParams['exclude'])) ? strtoupper($arrParams['exclude']) : '';
		$exclude .= (isset($arrParams['include']) && strpos(strtoupper($arrParams['include']),'AIR') !== FALSE) ? '' : ',AIR';

        $current_x = $this->objStartingVector->x;
		$current_y = $this->objStartingVector->y + $intElevation;
		$current_z = $this->objStartingVector->z;

        $xMult = 1;
        $zMult = 1;
        $xDim = $intLength;
        $zDim = $intWidth;

        switch($this->objStartingDirection)
        {
            case self::DIR_NORTH:
                $xMult = 1;
                $zMult = 1;
                $xDim = $intLength;
                $zDim = $intWidth;
            break;
            case self::DIR_EAST:
                $xMult = -1;
                $zMult = 1;
                $xDim = $intWidth;
                $zDim = $intLength;
            break;
            case self::DIR_SOUTH:
                $xMult = -1;
                $zMult = -1;
                $xDim = $intLength;
                $zDim = $intWidth;
            break;
            case self::DIR_WEST:
                $xMult = 1;
                $zMult = -1;
                $xDim = $intWidth;
                $zDim = $intLength;
            break;
        }

        $arrClip = array();
	    for($y = 0; $y <= $intHeight; $y++){
    		for($x = $xDim * $xMult; $x != 0; $x-=$xMult){
				for($z = $zDim * $zMult; $z != 0; $z-=$zMult){

					$block_pos = new Vector3($current_x + $x, $current_y + $y, $current_z + $z);
                    $objBlock = $objIssuer->getLevel()->getBlock($block_pos);

                    if(strpos($exclude,str_replace(' ','',strtoupper($objBlock->getName()))) !== FALSE) continue;

					$arrClip[] = $x.'|'.$y.'|'.$z.'|'.$objBlock->getId().'|'.$objBlock->getDamage();
				}
			}
		}

        $this->server->getLogger()->info($objIssuer->getName() . ': ' . $arrParams['name'] . ' copy complete. Saving to disk...');
        $clipConfig = new Config($this->server->getDataPath() . '../' . 'draw.clips.yml', Config::YAML, array());
        $clipConfig->set($arrParams['name'].'.d', $this->objStartingDirection);
        $clipConfig->set($arrParams['name'], json_encode($arrClip));
        $clipConfig->save();

		return $this->arrReturnMessage['copy'];
	}

    private function __fncPaste($arrParams, $objIssuer)
	{
	    $clipConfig = new Config($this->server->getDataPath() . '../' . 'draw.clips.yml', Config::YAML, array());
		$intElevation = (isset($arrParams['elevation']) && is_numeric ($arrParams['elevation'])) ? (int) $arrParams['elevation'] : $this->arrDefaults->get($objIssuer->getName())['elevation'];
	    $copyStartingDirection = $clipConfig->get($arrParams['name'].'.d');

        $current_x = $this->objStartingVector->x;
		$current_y = $this->objStartingVector->y + $intElevation;
		$current_z = $this->objStartingVector->z;

	    $arrClip = json_decode($clipConfig->get($arrParams['name']), true);
	    //   TODO CNielsen: Tell the user if $arrParams['name'] doesn't match a name in the file. Give them a list of options (see play command)
		foreach($arrClip as $arrCurrentClip)
		{
		    list($x, $y, $z, $t, $d) = explode("|", $arrCurrentClip);
		    if($copyStartingDirection == $this->objStartingDirection) {
		        // Do nothing. Original and new directions already match
		    } else if (($copyStartingDirection + 2) % 4 == $this->objStartingDirection) {
		        // Opposite direction, change signs
		        $x = -$x;
		        $z = -$z;
		    } else {
		        // Switch x and z
		        $c = $x;
		        $x = $z;
		        $z = $c;

                if ($copyStartingDirection == self::DIR_EAST || $copyStartingDirection == self::DIR_WEST) {
                    // Started from east/west, change signs
		            $x = -$x;
		            $z = -$z;
                }

		        //flip z on:
		        //    WEST -> NORTH, NORTH -> WEST
		        //    SOUTH -> EAST, EAST -> SOUTH
		        if (($copyStartingDirection == self::DIR_NORTH && $this->objStartingDirection == self::DIR_WEST)
		            || ($copyStartingDirection == self::DIR_WEST && $this->objStartingDirection == self::DIR_NORTH)
		            || ($copyStartingDirection == self::DIR_SOUTH && $this->objStartingDirection == self::DIR_EAST)
		            || ($copyStartingDirection == self::DIR_EAST && $this->objStartingDirection == self::DIR_SOUTH) ) {
		            $z = -$z;
		        }
		        //flip x on:
		        //    NORTH -> EAST, EAST -> NORTH
		        //    SOUTH -> WEST, WEST -> SOUTH
		        if (($copyStartingDirection == self::DIR_NORTH && $this->objStartingDirection == self::DIR_EAST)
		            || ($copyStartingDirection == self::DIR_EAST && $this->objStartingDirection == self::DIR_NORTH)
		            || ($copyStartingDirection == self::DIR_SOUTH && $this->objStartingDirection == self::DIR_WEST)
		            || ($copyStartingDirection == self::DIR_WEST && $this->objStartingDirection == self::DIR_SOUTH) ) {
		            $x = -$x;
		        }
		    }

			$objBlock = Block::get($t, $d);
			$block_pos = new Vector3($current_x + $x, $current_y + $y, $current_z + $z);

            $this->__fncSetRollback($objIssuer,$block_pos);
			$objIssuer->getLevel()->setBlock($block_pos, $objBlock);
		}

		return $this->arrReturnMessage['paste'];
	}


    private function __fncMeasure($arrParams, $objIssuer)
	{
        $current_x = $this->objStartingVector->x;
		$current_y = $this->objStartingVector->y;
		$current_z = $this->objStartingVector->z;

		$objItemInHand = $objIssuer->getInventory()->getItemInHand();
        if($objItemInHand instanceof ItemBlock) {
            $objBlockInHand = Block::get($objItemInHand->getId(), $objItemInHand->getDamage());
        } else {
            $objItemInHand = Item::get(Item::AIR);
            $objBlockInHand = Block::get($objItemInHand->getId(), $objItemInHand->getDamage());
        }

        $dist = -1;
        $xErrorMin = 0;
        $xErrorMax = 0;
        $zErrorMin = 0;
        $zErrorMax = 0;
        switch($this->objStartingDirection)
        {
            case self::DIR_NORTH:
                $zErrorMin = -1;
                $zErrorMax = 1;
            break;
            case self::DIR_EAST:
                $xErrorMin = -1;
                $xErrorMax = 1;
            break;
            case self::DIR_SOUTH:
                $zErrorMin = -1;
                $zErrorMax = 1;
            break;
            case self::DIR_WEST:
                $xErrorMin = -1;
                $xErrorMax = 1;
            break;
        }
        for($i = 0 ; $i <= 125 ; $i++) {        // After about 125, this stops working

            switch($this->objStartingDirection)
            {
                case self::DIR_NORTH:
                    $block_pos = new Vector3($current_x + $i, $current_y, $current_z);
                break;
                case self::DIR_EAST:
                    $block_pos = new Vector3($current_x, $current_y, $current_z + $i);
                break;
                case self::DIR_SOUTH:
                    $block_pos = new Vector3($current_x - $i, $current_y, $current_z);
                break;
                case self::DIR_WEST:
                    $block_pos = new Vector3($current_x, $current_y, $current_z - $i);
                break;
            }

            $foundIt = false;
            for($x = $xErrorMin; $x <= $xErrorMax && !$foundIt; $x++) {
                for($y = -1; $y < 5 && !$foundIt; $y++) {
                    for($z = $zErrorMin; $z <= $zErrorMax && !$foundIt; $z++) {
                        $try_block_pos = new Vector3($block_pos->getX()+$x, $block_pos->getY()+$y, $block_pos->getZ()+$z);
                        $objBlock = $objIssuer->getLevel()->getBlock($try_block_pos);
                        if($objBlock->getId() == $objBlockInHand->getId() && $objBlock->getDamage() == $objBlockInHand->getDamage()) {
                            $foundIt = true;
                        }
                    }
                }
            }

            if($foundIt) {
                $dist = $i;
                break;
            }
        }

        $message = '';
        if($dist > -1) {
            // Found it
            $message = 'Distance to next '.$objBlockInHand->GetName().' block is: '.$dist.'. Including last block, excluding block on which you are standing.';
        } else {
            $message = 'Distance to next '.$objBlockInHand->GetName().' block is too far to calculate';
        }

		return $message;
	}

	private function __fncUndo($arrParams, $objIssuer)
	{
		foreach(array_reverse($this->arrRollback[$objIssuer->getName()]) as $arrCurrentRollback)
		{
			$objBlock = Block::get($arrCurrentRollback['block_type'], $arrCurrentRollback['block_meta']);
			$objIssuer->getLevel()->setBlock($arrCurrentRollback['block_pos'], $objBlock);
		}

		$this->arrRollback[$objIssuer->getName()] = array();
		return $this->arrReturnMessage['undo'];
	}

	private function __fncSetRollback($objIssuer,$block_pos)
	{
		$objCurrentBlock = $objIssuer->getLevel()->getBlock($block_pos);
		$intCurrentBlockID = $objCurrentBlock->getId();
		$intCurrentBlockMeta = $objCurrentBlock->getDamage();
		$this->arrRollback[$objIssuer->getName()][] = array('block_pos'=>$block_pos,'block_type'=>$intCurrentBlockID, 'block_meta'=>$intCurrentBlockMeta);
	}

	private function __fncDrawFloor($arrParams, $objIssuer)
	{
		//first get the coordinates of where the user is standing
		$intLength = (isset($arrParams['length']) && is_numeric ($arrParams['length'])) ? (int) $arrParams['length'] : $this->arrDefaults->get($objIssuer->getName())['length'];
		$intWidth = (isset($arrParams['width']) && is_numeric ($arrParams['width'])) ? (int) $arrParams['width'] : $this->arrDefaults->get($objIssuer->getName())['width'];
		$intElevation = (isset($arrParams['elevation']) && is_numeric ($arrParams['elevation'])) ? (int) $arrParams['elevation'] : -1;

        $current_x = $this->objStartingVector->x;
		$current_y = $this->objStartingVector->y + $intElevation;
		$current_z = $this->objStartingVector->z;

		$objItem = $objIssuer->getInventory()->getItemInHand();

		$block_pos = new Vector3($current_x, $current_y, $current_z);
		$arrRectangle = $this->__fncDrawRectangle(array('objIssuer'=>$objIssuer,'strStaticPlain'=>'horizontal','intLength'=>$intLength,'intWidth'=>$intWidth,'objStartingPos'=>$block_pos,'objItem'=>$objItem));

		return $this->arrReturnMessage['floor'];
	}

    private function __fncDrawGroundReplace($arrParams, $objIssuer) {
        return $this->__fncDrawGroundCover($arrParams, $objIssuer, 0);
    }

	private function __fncDrawGroundCover($arrParams, $objIssuer, $intElevation = 1)
	{
		$intLength = (isset($arrParams['length']) && is_numeric ($arrParams['length'])) ? (int) $arrParams['length'] : $this->arrDefaults->get($objIssuer->getName())['length'];
		$intWidth = (isset($arrParams['width']) && is_numeric ($arrParams['width'])) ? (int) $arrParams['width'] : $this->arrDefaults->get($objIssuer->getName())['width'];
        $objItem = $objIssuer->getInventory()->getItemInHand();

        $current_x = $this->objStartingVector->x;
		$current_y = $this->objStartingVector->y;
		$current_z = $this->objStartingVector->z;

        $xMult = 1;
        $zMult = 1;
        $xDim = $intLength;
        $zDim = $intWidth;

        switch($this->objStartingDirection)
        {
            case self::DIR_NORTH:
                $xMult = 1;
                $zMult = 1;
                $xDim = $intLength;
                $zDim = $intWidth;
            break;
            case self::DIR_EAST:
                $xMult = -1;
                $zMult = 1;
                $xDim = $intWidth;
                $zDim = $intLength;
            break;
            case self::DIR_SOUTH:
                $xMult = -1;
                $zMult = -1;
                $xDim = $intLength;
                $zDim = $intWidth;
            break;
            case self::DIR_WEST:
                $xMult = 1;
                $zMult = -1;
                $xDim = $intWidth;
                $zDim = $intLength;
            break;
        }

        if($objItem instanceof ItemBlock) {
            $objBlock = Block::get($objItem->getId(), $objItem->getDamage());
        } else {
            $objItem = Item::get(Item::AIR);
            $objBlock = Block::get($objItem->getId(), $objItem->getDamage());
        }

        for($x = $xDim * $xMult; $x != 0; $x-=$xMult){
            for($z = $zDim * $zMult; $z != 0; $z-=$zMult){

                $objHighestBlock = $objIssuer->getLevel()->getHighestBlockAt($current_x + $x, $current_z + $z);

                $block_pos = new Vector3($current_x + $x, $objHighestBlock, $current_z + $z);
                $objExistingBlock = $objIssuer->getLevel()->getBlock($block_pos);
                while ($objHighestBlock > 0 && $objExistingBlock->canBeReplaced()) {
                    $objHighestBlock--;
                    $block_pos = new Vector3($current_x + $x, $objHighestBlock, $current_z + $z);
                    $objExistingBlock = $objIssuer->getLevel()->getBlock($block_pos);
                }

                $block_pos = new Vector3($current_x + $x, $objHighestBlock + $intElevation, $current_z + $z);
                $this->__fncSetRollback($objIssuer,$block_pos);

                $objIssuer->getLevel()->setBlock($block_pos, $objBlock);
            }
        }

		return $this->arrReturnMessage['groundcover'];
	}

	private function __fncDrawPool($arrParams, $objIssuer)
	{
		//first get the coordinates of where the user is standing
		$intLength = (isset($arrParams['length']) && is_numeric ($arrParams['length'])) ? (int) $arrParams['length'] : $this->arrDefaults->get($objIssuer->getName())['length'];
		$intWidth = (isset($arrParams['width']) && is_numeric ($arrParams['width'])) ? (int) $arrParams['width'] : $this->arrDefaults->get($objIssuer->getName())['width'];
		$intDepth = (isset($arrParams['depth']) && is_numeric ($arrParams['depth'])) ? (int) $arrParams['depth'] : $this->arrDefaults->get($objIssuer->getName())['depth'];

		$objItem = Item::get(Item::STILL_WATER);

        $current_x = $this->objStartingVector->x;
		$current_y = $this->objStartingVector->y;
		$current_z = $this->objStartingVector->z;

		for($i=1;$i<=$intDepth;$i++)
		{
			$block_pos = new Vector3($current_x, $current_y - $i, $current_z);
			$arrRectangle = $this->__fncDrawRectangle(array('objIssuer'=>$objIssuer,'strStaticPlain'=>'horizontal','intLength'=>$intLength,'intWidth'=>$intWidth,'objStartingPos'=>$block_pos,'objItem'=>$objItem));
		}


		return $this->arrReturnMessage['pool'];
	}

	private function __fncDrawLavaLake($arrParams, $objIssuer)
	{
		//first get the coordinates of where the user is standing
		$intLength = (isset($arrParams['length']) && is_numeric ($arrParams['length'])) ? (int) $arrParams['length'] : $this->arrDefaults->get($objIssuer->getName())['length'];
		$intWidth = (isset($arrParams['width']) && is_numeric ($arrParams['width'])) ? (int) $arrParams['width'] : $this->arrDefaults->get($objIssuer->getName())['width'];
		$intDepth = (isset($arrParams['depth']) && is_numeric ($arrParams['depth'])) ? (int) $arrParams['depth'] : $this->arrDefaults->get($objIssuer->getName())['depth'];

		$objItem = Item::get(Item::STILL_LAVA);

        $current_x = $this->objStartingVector->x;
		$current_y = $this->objStartingVector->y;
		$current_z = $this->objStartingVector->z;

		for($i=1;$i<=$intDepth;$i++)
		{
			$block_pos = new Vector3($current_x, $current_y - $i, $current_z);
			$arrRectangle = $this->__fncDrawRectangle(array('objIssuer'=>$objIssuer,'strStaticPlain'=>'horizontal','intLength'=>$intLength,'intWidth'=>$intWidth,'objStartingPos'=>$block_pos,'objItem'=>$objItem));
		}


		return $this->arrReturnMessage['lavalake'];
	}

	private function __fncCut($arrParams, $objIssuer)
	{
		//short cut function to remove blocks (paste coming soon). Calls draw prism and passes in air.
		$arrParams['objItem'] = Item::get(Item::AIR);
		$this-> __fncDrawPrism($arrParams, $objIssuer);

		return $this->arrReturnMessage['cut'];

	}

	private function __fncReplace($arrParams, $objIssuer)
	{
		//short cut function to replace blocks
		$this-> __fncDrawPrism($arrParams, $objIssuer);
		return $this->arrReturnMessage['replace'];

	}

	private function __fncDrawSteps($arrParams, $objIssuer)
	{
		//incoming parameters
		$intWidth = (isset($arrParams['width']) && is_numeric ($arrParams['width'])) ? (int) $arrParams['width'] : $this->arrDefaults->get($objIssuer->getName())['width'];
		$intHeight = (isset($arrParams['height']) && is_numeric ($arrParams['height'])) ? (int) $arrParams['height'] : $this->arrDefaults->get($objIssuer->getName())['height'];
		$intElevation = (isset($arrParams['elevation']) && is_numeric ($arrParams['elevation'])) ? (int) $arrParams['elevation'] : $this->arrDefaults->get($objIssuer->getName())['elevation'];
		$objItem = $objIssuer->getInventory()->getItemInHand();


        $current_x = $this->objStartingVector->x;
		$current_y = $this->objStartingVector->y + $intElevation;
		$current_z = $this->objStartingVector->z;


		$intLength = $intHeight;
		for($i=0;$i<$intHeight;$i++)
		{
			switch($this->objStartingDirection)
			{
				case self::DIR_NORTH:
					$block_pos = new Vector3($current_x + $i, $current_y + $i, $current_z);
				break;
				case self::DIR_EAST:
					$block_pos = new Vector3($current_x, $current_y + $i, $current_z + $i);
				break;
				case self::DIR_SOUTH:
					$block_pos = new Vector3($current_x - $i, $current_y + $i, $current_z);
				break;
				case self::DIR_WEST:
					$block_pos = new Vector3($current_x, $current_y + $i, $current_z - $i);
				break;
			}
			$arrRectangle = $this->__fncDrawRectangle(array('objIssuer'=>$objIssuer,'strStaticPlain'=>'horizontal','intLength'=>$intLength,'intWidth'=>$intWidth,'objStartingPos'=>$block_pos,'objItem'=>$objItem));
			$intLength--;
		}


		return $this->arrReturnMessage['steps'];
	}

	private function __fncDrawDiamond($arrParams, $objIssuer)
	{
		$intSize = (isset($arrParams['size']) && is_numeric ($arrParams['size'])) ? (int) $arrParams['size'] : $this->arrDefaults->get($objIssuer->getName())['size'];
		$intElevation = (isset($arrParams['elevation']) && is_numeric ($arrParams['elevation'])) ? (int) $arrParams['elevation'] : $this->arrDefaults->get($objIssuer->getName())['elevation'];
		$objItem = $objIssuer->getInventory()->getItemInHand();

        $current_x = $this->objStartingVector->x;
		$current_y = $this->objStartingVector->y + $intElevation;
		$current_z = $this->objStartingVector->z;

		$intPositionAdjustment = $intSize/2 -1;

		if ($intSize % 2 == 0)
		{
			$intStartingSize = 2;
		}
		else
		{
			$intStartingSize = 1;
		}

		$blnIncreaseSize = 1;

		for($i=0;$i<$intSize;$i++)
		{
			switch($this->objStartingDirection)
			{
				case self::DIR_NORTH:
					$block_pos = new Vector3($current_x + $intPositionAdjustment, $current_y + $i, $current_z + $intPositionAdjustment);
				break;
				case self::DIR_EAST:
					$block_pos = new Vector3($current_x - $intPositionAdjustment, $current_y + $i, $current_z + $intPositionAdjustment);
				break;
				case self::DIR_SOUTH:
					$block_pos = new Vector3($current_x - $intPositionAdjustment, $current_y + $i, $current_z - $intPositionAdjustment);
				break;
				case self::DIR_SOUTH:
					$block_pos = new Vector3($current_x + $intPositionAdjustment, $current_y + $i, $current_z - $intPositionAdjustment);
				break;
			}

			$arrRectangle = $this->__fncDrawRectangle(array('objIssuer'=>$objIssuer,'strStaticPlain'=>'horizontal','intLength'=>$intStartingSize,'intWidth'=>$intStartingSize,'objStartingPos'=>$block_pos,'objItem'=>$objItem));

			if($intStartingSize >= $intSize)
			{
				$blnIncreaseSize = 0;
			}
			if($blnIncreaseSize)
			{
				$intStartingSize = $intStartingSize + 2;
				$intPositionAdjustment--;
			}
			else
			{
				$intStartingSize = $intStartingSize - 2;
				$intPositionAdjustment++;
			}
		}
		return $this->arrReturnMessage['diamond'];
	}

	private function __fncDrawBubble($arrParams, $objIssuer)
	{
	    $this->__fncDrawSphere($arrParams, $objIssuer, true);
        return $this->arrReturnMessage['bubble'];
	}

	private function __fncDrawSphere($arrParams, $objIssuer, $hollow = false)
	{
		$intRadius = (isset($arrParams['radius']) && is_numeric ($arrParams['radius'])) ? (int) $arrParams['radius'] : $this->arrDefaults->get($objIssuer->getName())['radius'];
		$intElevation = (isset($arrParams['elevation']) && is_numeric ($arrParams['elevation'])) ? (int) $arrParams['elevation'] : $this->arrDefaults->get($objIssuer->getName())['elevation'];
		$aroundMe = (isset($arrParams['aroundme']) && $arrParams['aroundme'] == 't') ? true : false;
        $objItem = $objIssuer->getInventory()->getItemInHand();

        if($hollow || $aroundMe) {
            $current_x = $this->objStartingVector->x;
            $current_y = $this->objStartingVector->y + $intElevation;
            $current_z = $this->objStartingVector->z;
        } else {
            $current_y = $this->objStartingVector->y + $intRadius + $intElevation;
            switch($this->objStartingDirection)
            {
                case self::DIR_NORTH:
                    $current_x = $this->objStartingVector->x + $intRadius;
                    $current_z = $this->objStartingVector->z + $intRadius;
                break;
                case self::DIR_EAST:
                    $current_x = $this->objStartingVector->x - $intRadius;
                    $current_z = $this->objStartingVector->z + $intRadius;
                break;
                case self::DIR_SOUTH:
                    $current_x = $this->objStartingVector->x - $intRadius;
                    $current_z = $this->objStartingVector->z - $intRadius;
                break;
                case self::DIR_WEST:
                    $current_x = $this->objStartingVector->x + $intRadius;
                    $current_z = $this->objStartingVector->z - $intRadius;
                break;
            }
        }

        if($objItem instanceof ItemBlock) {
            $objBlock = Block::get($objItem->getId(), $objItem->getDamage());
        } else {
            $objItem = Item::get(Item::AIR);
            $objBlock = Block::get($objItem->getId(), $objItem->getDamage());
        }

		for($x = $intRadius; $x >= -$intRadius; $x--){
			for($y = $intRadius; $y >= -$intRadius; $y--){
				for($z = $intRadius; $z >= -$intRadius; $z--){
					$intDist = sqrt(($x*$x + $y*$y + $z*$z)); //Calculates the distance
					if($intDist > $intRadius) continue;
					if ($hollow) {
                        if ($intDist < $intRadius - 1.414213562373095) continue;
                    }

					$block_pos = new Vector3($current_x + $x, $current_y + $y, $current_z - $z);
					$this->__fncSetRollback($objIssuer,$block_pos);

					$objIssuer->getLevel()->setBlock($block_pos, $objBlock);
				}
			}
		}

		return $this->arrReturnMessage['sphere'];
	}

	private function __fncDrawVolcano($arrParams, $objIssuer)
	{
		$intRadius = (isset($arrParams['radius']) && is_numeric ($arrParams['radius'])) ? (int) $arrParams['radius'] : $this->arrDefaults->get($objIssuer->getName())['radius'];
		$intHeight = (isset($arrParams['height']) && is_numeric ($arrParams['height'])) ? (int) $arrParams['height'] : $this->arrDefaults->get($objIssuer->getName())['height'];
		$intElevation = (isset($arrParams['elevation']) && is_numeric ($arrParams['elevation'])) ? (int) $arrParams['elevation'] : $this->arrDefaults->get($objIssuer->getName())['elevation'];
        $objItem = $objIssuer->getInventory()->getItemInHand();

		$intHeight = $intHeight / 2;

        $current_x = $this->objStartingVector->x;
        $current_y = $this->objStartingVector->y + $intElevation + $intHeight;
        $current_z = $this->objStartingVector->z;

        if($objItem instanceof ItemBlock) {
            $objBlock = Block::get($objItem->getId(), $objItem->getDamage());
        } else {
            $objItem = Item::get(Item::AIR);
            $objBlock = Block::get($objItem->getId(), $objItem->getDamage());
        }

        $circleSize = $intRadius;
        for($y = $intHeight; $y >= -$intHeight; $y--){
            $prevCircleSize = $circleSize;
            $multiplier = -pow(((($intHeight * 2 - ($y + $intHeight)) / ($intHeight / 5)) / 6.75), 6) + 10;
            if ($y + $intHeight == 0) {
                $circleSize = $multiplier * $intRadius;
            } else {
                $circleSize = ($multiplier * $intRadius) / ($y + $intHeight);
            }
		    for($x = $intRadius; $x >= -$intRadius; $x--){
				for($z = $intRadius; $z >= -$intRadius; $z--){
					$intDist = sqrt(($x*$x + $z*$z)); //Calculates the distance

                    if ($intDist > $circleSize) continue;
                    if ($intDist < $prevCircleSize - 1.414213562373095) {
                        $objInnerItem = Item::get(Item::AIR);
                        $objInnerBlock = Block::get($objInnerItem->getId(), $objInnerItem->getDamage());

                        $block_pos = new Vector3($current_x + $x, $current_y + $y, $current_z - $z);
                        $this->__fncSetRollback($objIssuer,$block_pos);

                        $objIssuer->getLevel()->setBlock($block_pos, $objInnerBlock);
                        continue;
                    }

					$block_pos = new Vector3($current_x + $x, $current_y + $y, $current_z - $z);
					$this->__fncSetRollback($objIssuer,$block_pos);

					$objIssuer->getLevel()->setBlock($block_pos, $objBlock);
				}
			}
		}

		return $this->arrReturnMessage['volcano'];
	}

	private function __fncSetDefaults($arrParams, $objIssuer)
	{
		$blnNeedSaved = 0;

		foreach($arrParams AS $currentKey=>$currentValue)
		{
			if(isset($this->arrDefaults->get($objIssuer->getName())[$currentKey]))
			{
				$this->arrDefaults->get($objIssuer->getName())[$currentKey] = $currentValue;
				$blnNeedSaved = 1;
			}
		}

		if($blnNeedSaved)
		{
			$this->arrDefaults->save();
			return $this->arrReturnMessage['set_defaults'];
		}
		else
		{
			return $this->arrReturnMessage['error_defaults'];
		}

	}

	private function __fncDrawPrism($arrParams, $objIssuer)
	{
		//incoming parameters
		$intLength = (isset($arrParams['length']) && is_numeric ($arrParams['length'])) ? (int) $arrParams['length'] : $this->arrDefaults->get($objIssuer->getName())['length'];
		$intWidth = (isset($arrParams['width']) && is_numeric ($arrParams['width'])) ? (int) $arrParams['width'] : $this->arrDefaults->get($objIssuer->getName())['width'];
		$intHeight = (isset($arrParams['height']) && is_numeric ($arrParams['height'])) ? (int) $arrParams['height'] : $this->arrDefaults->get($objIssuer->getName())['height'];
		$intElevation = (isset($arrParams['elevation']) && is_numeric ($arrParams['elevation'])) ? (int) $arrParams['elevation'] : $this->arrDefaults->get($objIssuer->getName())['elevation'];
        $objItem = (isset($arrParams['objItem'])) ? $arrParams['objItem'] : $objIssuer->getInventory()->getItemInHand();

        // Everything seems to start from the block in front of you while looking north.
        //  So we need to start by subtracting one from x for every direction
        echo 'Current x: '. $this->objStartingVector->x;
        $current_x = $this->objStartingVector->x;
		$current_y = $this->objStartingVector->y + $intElevation;
		$current_z = $this->objStartingVector->z;

		for($i=0;$i<$intHeight;$i++)
		{
			$block_pos = new Vector3($current_x, $current_y + $i, $current_z);
			$arrRectangle = $this->__fncDrawRectangle(array('objIssuer'=>$objIssuer,'strStaticPlain'=>'horizontal','intLength'=>$intLength,'intWidth'=>$intWidth,'objStartingPos'=>$block_pos,'objItem'=>$objItem));
		}


		return $this->arrReturnMessage['prism'];
	}

	private function __fncDrawCube($arrParams, $objIssuer)
	{
		//first get the coordinates of where the user is standing
		$intSize = (isset($arrParams['size']) && is_numeric ($arrParams['size'])) ? (int) $arrParams['size'] : $this->arrDefaults->get($objIssuer->getName())['length'];
		$intElevation = (isset($arrParams['elevation']) && is_numeric ($arrParams['elevation'])) ? (int) $arrParams['elevation'] : $this->arrDefaults->get($objIssuer->getName())['elevation'];
        $objItem = $objIssuer->getInventory()->getItemInHand();

        $current_x = $this->objStartingVector->x;
		$current_y = $this->objStartingVector->y + $intElevation;
		$current_z = $this->objStartingVector->z;

		for($i=0;$i<$intSize;$i++)
		{
			$block_pos = new Vector3($current_x, $current_y + $i, $current_z);
			$arrRectangle = $this->__fncDrawRectangle(array('objIssuer'=>$objIssuer,'strStaticPlain'=>'horizontal','intLength'=>$intSize,'intWidth'=>$intSize,'objStartingPos'=>$block_pos,'objItem'=>$objItem));
		}

		return $this->arrReturnMessage['cube'];
	}

	private function __fncDrawPyramid($arrParams, $objIssuer)
	{
		//first get the coordinates of where the user is standing
		$intSize = (isset($arrParams['size']) && is_numeric ($arrParams['size'])) ? (int) $arrParams['size'] : $this->arrDefaults->get($objIssuer->getName())['length'];
		$intElevation = (isset($arrParams['elevation']) && is_numeric ($arrParams['elevation'])) ? (int) $arrParams['elevation'] : $this->arrDefaults->get($objIssuer->getName())['elevation'];
        $objItem = $objIssuer->getInventory()->getItemInHand();

        $current_x = $this->objStartingVector->x;
		$current_y = $this->objStartingVector->y + $intElevation;
		$current_z = $this->objStartingVector->z;
		$intCurrentSize = $intSize;

		for($i=0;$i< $intSize/2; $i++)
		{
			switch($this->objStartingDirection)
			{
				case self::DIR_NORTH:
					$block_pos = new Vector3($current_x + $i, $current_y + $i, $current_z + $i);
				break;
				case self::DIR_EAST:
					$block_pos = new Vector3($current_x - $i, $current_y + $i, $current_z + $i);
				break;
				case self::DIR_SOUTH:
					$block_pos = new Vector3($current_x - $i, $current_y + $i, $current_z - $i);
				break;
				case self::DIR_WEST:
					$block_pos = new Vector3($current_x + $i, $current_y + $i, $current_z - $i);
				break;
			}

    		$arrRectangle = $this->__fncDrawRectangle(array('objIssuer'=>$objIssuer,'strStaticPlain'=>'horizontal','intLength'=>$intCurrentSize,'intWidth'=>$intCurrentSize,'objStartingPos'=>$block_pos,'objItem'=>$objItem));
			$intCurrentSize = $intCurrentSize - 2;
		}

		return $this->arrReturnMessage['pyramid'];
	}

	private function __fncDrawTomb($arrParams, $objIssuer)
	{
		//first get the coordinates of where the user is standing
		$intSize = (isset($arrParams['size']) && is_numeric ($arrParams['size'])) ? (int) $arrParams['size'] : $this->arrDefaults->get($objIssuer->getName())['length'];
		$intElevation = (isset($arrParams['elevation']) && is_numeric ($arrParams['elevation'])) ? (int) $arrParams['elevation'] : $this->arrDefaults->get($objIssuer->getName())['elevation'];
        $objItem = $objIssuer->getInventory()->getItemInHand();
        $objAirItem = Item::get(Item::AIR);

        $current_x = $this->objStartingVector->x;
		$current_y = $this->objStartingVector->y + $intElevation;
		$current_z = $this->objStartingVector->z;
		$intCurrentSize = $intSize;

		for($i=0;$i< $intSize/2; $i++)
		{
			switch($this->objStartingDirection)
			{
				case self::DIR_NORTH:
					$block_pos = new Vector3($current_x + $i, $current_y + $i, $current_z + $i);
				break;
				case self::DIR_EAST:
					$block_pos = new Vector3($current_x - $i, $current_y + $i, $current_z + $i);
				break;
				case self::DIR_SOUTH:
					$block_pos = new Vector3($current_x - $i, $current_y + $i, $current_z - $i);
				break;
				case self::DIR_WEST:
					$block_pos = new Vector3($current_x + $i, $current_y + $i, $current_z - $i);
				break;
			}

    		$arrRectangle = $this->__fncDrawRectangle(array('objIssuer'=>$objIssuer,'strStaticPlain'=>'horizontal','intLength'=>$intCurrentSize,'intWidth'=>$intCurrentSize,'objStartingPos'=>$block_pos,'objItem'=>$objItem));
    		if ($i > 1) {
    		    // Hollow it out
    		    $block_pos->setComponents($block_pos->getX(), $block_pos->getY()-1, $block_pos->getZ());
    		    $arrRectangle = $this->__fncDrawRectangle(array('objIssuer'=>$objIssuer,'strStaticPlain'=>'horizontal','intLength'=>$intCurrentSize,'intWidth'=>$intCurrentSize,'objStartingPos'=>$block_pos,'objItem'=>$objAirItem));
    		 }
			$intCurrentSize = $intCurrentSize - 2;
		}

		return $this->arrReturnMessage['tomb'];
	}



	private function __fncDrawWall($arrParams, $objIssuer)
	{
        $current_x = $this->objStartingVector->x;
		$current_y = $this->objStartingVector->y;
		$current_z = $this->objStartingVector->z;

		$intLength = (isset($arrParams['length']) && is_numeric ($arrParams['length'])) ? (int) $arrParams['length'] : $this->arrDefaults->get($objIssuer->getName())['length'];
		$intHeight = (isset($arrParams['height']) && is_numeric ($arrParams['height'])) ? (int) $arrParams['height'] : $this->arrDefaults->get($objIssuer->getName())['width'];
		$intElevation = (isset($arrParams['elevation']) && is_numeric ($arrParams['elevation'])) ? (int) $arrParams['elevation'] : $this->arrDefaults->get($objIssuer->getName())['elevation'];
        $objItem = $objIssuer->getInventory()->getItemInHand();

		$block_pos = new Vector3($current_x, $current_y + $intElevation, $current_z);
		$arrRectangle = $this->__fncDrawRectangle(array('objIssuer'=>$objIssuer,'strStaticPlain'=>'vertical','intLength'=>$intLength,'intWidth'=>$intHeight,'objStartingPos'=>$block_pos,'objItem'=>$objItem));
		return $this->arrReturnMessage['wall'];
	}


	//This can probably get cleaned up, one of the earlier functions
	private function __fncDrawBox($arrParams, $objIssuer)
	{
		$intSize = (isset($arrParams['size']) && is_numeric ($arrParams['size'])) ? (int) $arrParams['size'] : $this->arrDefaults->get($objIssuer->getName())['size'];
		$intElevation = (isset($arrParams['elevation']) && is_numeric ($arrParams['elevation'])) ? (int) $arrParams['elevation'] : $this->arrDefaults->get($objIssuer->getName())['elevation'];
		$objItem = $objIssuer->getInventory()->getItemInHand();

		//first get the coordinates of where the user is standing
        // Everything seems to start from the block in front of you while looking north.
        //  So we need to start by subtracting one from x for every direction
		$current_x = (int) $this->objStartingVector->x + 1 - 1;
		$current_y = (int) $this->objStartingVector->y + $intElevation;
		$current_z = (int) $this->objStartingVector->z + 1;

		switch($this->objStartingDirection)
		{
			case self::DIR_NORTH:
				$objFrontWallPos = new Vector3($current_x + 1, $current_y, $current_z - 1 );
				$objBackWallPos = new Vector3($current_x + $intSize, $current_y, $current_z);
				$objSideWall = new Vector3($current_x + 1, $current_y, $current_z);
				$objOppositeSideWallPos = new Vector3($current_x, $current_y, $current_z + $intSize - 1);

				$objFloor = new Vector3($current_x, $current_y, $current_z);
				$objCeiling = new Vector3($current_x, $current_y + $intSize - 1, $current_z);
				$intOppositeDirection = (int) self::DIR_EAST;
			break;
			case self::DIR_EAST:
				$objFrontWallPos = new Vector3($current_x + 1, $current_y, $current_z + 1);
				$objBackWallPos = new Vector3($current_x, $current_y, $current_z + $intSize);
				$objSideWall = new Vector3($current_x, $current_y, $current_z + 1);
				$objOppositeSideWallPos = new Vector3($current_x - $intSize + 1, $current_y, $current_z);
				$objFloor = new Vector3($current_x, $current_y, $current_z);
				$objCeiling = new Vector3($current_x, $current_y + $intSize - 1, $current_z);
				$intOppositeDirection = (int) self::DIR_SOUTH;
			break;
			case self::DIR_SOUTH:
				$objFrontWallPos = new Vector3($current_x - 2, $current_y, $current_z );
				$objBackWallPos = new Vector3($current_x - $intSize -1, $current_y, $current_z + 1);
				$objSideWall = new Vector3($current_x - 1, $current_y, $current_z);
				$objOppositeSideWallPos = new Vector3($current_x - 2, $current_y, $current_z - $intSize + 1);
				$objFloor = new Vector3($current_x - 1, $current_y, $current_z);
				$objCeiling = new Vector3($current_x -1, $current_y + $intSize - 1, $current_z);
				$intOppositeDirection = (int) self::DIR_WEST;
			break;
			case self::DIR_WEST:
				$objFrontWallPos = new Vector3($current_x, $current_y, $current_z - 3);
				$objBackWallPos = new Vector3($current_x - 1, $current_y, $current_z - $intSize - 2);
				$objSideWall = new Vector3($current_x, $current_y, $current_z - 2);
				$objOppositeSideWallPos = new Vector3($current_x + $intSize - 1, $current_y, $current_z - 3);
				$objFloor = new Vector3($current_x, $current_y, $current_z - 2);
				$objCeiling = new Vector3($current_x, $current_y + $intSize - 1, $current_z - 2);
				$intOppositeDirection = (int) self::DIR_NORTH;
			break;
		}

		$arrRectangle = $this->__fncDrawRectangle(array('objIssuer'=>$objIssuer,'strStaticPlain'=>'vertical','intLength'=>$intSize - 1,'intWidth'=>$intSize - 1,'objStartingPos'=>$objSideWall,'objItem'=>$objItem));
		$arrRectangle = $this->__fncDrawRectangle(array('objIssuer'=>$objIssuer,'strStaticPlain'=>'vertical','intLength'=>$intSize - 1,'intWidth'=>$intSize - 1,'objStartingPos'=>$objOppositeSideWallPos,'objItem'=>$objItem));
		$arrRectangle = $this->__fncDrawRectangle(array('objIssuer'=>$objIssuer,'strStaticPlain'=>'vertical','intLength'=>$intSize - 1,'intWidth'=>$intSize - 1,'objStartingPos'=>$objFrontWallPos,'objItem'=>$objItem,'intCurrentDirection'=>$intOppositeDirection));
		$arrRectangle = $this->__fncDrawRectangle(array('objIssuer'=>$objIssuer,'strStaticPlain'=>'vertical','intLength'=>$intSize - 1,'intWidth'=>$intSize - 1,'objStartingPos'=>$objBackWallPos,'objItem'=>$objItem,'intCurrentDirection'=>$intOppositeDirection));
		$arrRectangle = $this->__fncDrawRectangle(array('objIssuer'=>$objIssuer,'strStaticPlain'=>'horizontal','intLength'=>$intSize,'intWidth'=>$intSize,'objStartingPos'=>$objFloor,'objItem'=>$objItem));
		$arrRectangle = $this->__fncDrawRectangle(array('objIssuer'=>$objIssuer,'strStaticPlain'=>'horizontal','intLength'=>$intSize,'intWidth'=>$intSize,'objStartingPos'=>$objCeiling,'objItem'=>$objItem));

		return $this->arrReturnMessage['box'];
	}

	private function __fncDrawString($arrParams, $objIssuer)
	{

		$objItem = $objIssuer->getInventory()->getItemInHand();
		$intElevation = (isset($arrParams['elevation']) && is_numeric ($arrParams['elevation'])) ? (int) $arrParams['elevation'] : $this->arrDefaults->get($objIssuer->getName())['elevation'];
        $objBackgroundItem = Item::get(Item::AIR);
        $objBackgroundBlock = Block::get($objBackgroundItem->getId(), $objBackgroundItem->getDamage());

		$arrFullString = str_split(strtolower($arrParams['text']));

		$arrSmall['a']=array(6,7,8,9,10,12,13,15,16,17,18,20,21,22,24,25,26,28,29,31,38,39);
		$arrSmall['b']=array(9,10,11,13,14,17,18,19,21,22,25,26,27,29,30,32,39);
		$arrSmall['c']=array(9,10,11,12,13,14,17,18,19,20,21,22,25,26,27,28,29,30,33,34,35,26,27,36,37,38);
		$arrSmall['d']=array(9,10,11,12,13,14,17,18,19,20,21,22,25,26,27,28,29,30,32,39);
		$arrSmall['e']=array(9,10,11,13,14,17,18,19,21,22,25,26,27,29,30,33,34,35,36,37,38);
		$arrSmall['f']=array(8,9,10,11,13,14,16,17,18,19,21,22,24,25,26,27,29,30,32,33,34,35,36,37,38);
		$arrSmall['g']=array(9,10,11,12,13,14,17,18,19,20,21,22,25,26,28,29,30,36,37,38);
		$arrSmall['h']=array(8,9,10,11,13,14,15,16,17,18,19,21,22,23,24,25,26,27,29,30,31);
		$arrSmall['i']=array(1,2,3,4,5,6,9,10,11,12,13,14,25,26,27,28,29,30,33,34,35,36,37,38);
		$arrSmall['j']=array(0,3,4,5,6,9,10,11,12,13,14,24,25,26,27,28,29,30,32,33,34,35,36,37,38);
		$arrSmall['k']=array(8,9,10,11,13,14,15,16,17,18,20,22,23,24,25,27,28,29,31,34,35,36,37,38);
		$arrSmall['l']=array(9,10,11,12,13,14,15,17,18,19,20,21,22,23,25,26,27,28,29,30,31,33,34,35,36,37,38,39);
		$arrSmall['m']=array(8,9,10,11,12,13,14,16,17,18,19,20,24,25,26,27,28,29,30);
		//$arrSmall['n']=array(7,8,9,10,11,12,13,16,17,18,19,20,21,22,24,25,26,27,28,29,30);
		$arrSmall['n']=array(15,12,11,10,9,8,23,22,21,18,17,16,31,30,29,28,27,24);
		$arrSmall['o']=array(9,10,11,12,13,14,17,18,19,20,21,22,25,26,27,28,29,30);
		$arrSmall['p']=array(8,9,10,11,13,14,16,17,18,19,21,22,24,25,26,27,29,30,32,33,34,35);
		$arrSmall['q']=array(0,8,10,11,12,13,14,16,18,19,20,21,22,24,33,34,35,36,37,38,39);
		$arrSmall['r']=array(8,9,10,13,14,16,17,18,21,22,24,25,27,29,30,34,35);
		$arrSmall['s']=array(1,2,3,9,10,11,13,14,17,18,19,21,22,25,26,27,29,30,37,38);
		$arrSmall['t']=array(0,1,2,3,4,5,6,8,9,10,11,12,13,14,24,25,26,27,28,29,30,32,33,34,35,36,37,38);
		$arrSmall['u']=array(9,10,11,12,13,14,15,17,18,19,20,21,22,23,25,26,27,28,29,30,31);
		$arrSmall['v']=array(0,1,8,10,11,12,13,14,15,17,18,19,20,21,22,23,24,26,27,28,29,30,31,32,33);
		$arrSmall['w']=array(9,10,11,12,13,14,15,19,20,21,22,23,25,26,27,28,29,30,31);
		$arrSmall['x']=array(2,3,4,5,8,9,11,12,14,15,16,17,18,21,22,23,24,25,27,28,30,31,34,35,36,37);
		$arrSmall['y']=array(0,1,2,3,8,9,10,11,13,14,15,21,22,23,24,25,26,27,29,30,31,32,33,34,35);
		$arrSmall['z']=array(2,3,4,5,6,9,11,12,13,14,17,18,20,21,22,25,26,27,29,30,33,34,35,36);


		switch($this->objStartingDirection)
		{
			case self::DIR_NORTH:
				$intOppositeDirection = (int) self::DIR_EAST;
			break;
			case self::DIR_EAST:
				$intOppositeDirection = (int) self::DIR_SOUTH;
			break;
			case self::DIR_SOUTH:
				$intOppositeDirection = (int) self::DIR_WEST;
			break;
			case self::DIR_WEST:
				$intOppositeDirection = (int) self::DIR_NORTH;
			break;
		}

        $current_x = $this->objStartingVector->x;
		$current_y = $this->objStartingVector->y + $intElevation;
		$current_z = $this->objStartingVector->z;
		$block_pos = new Vector3($current_x, $current_y, $current_z);
		foreach($arrFullString AS $strCurrentChar)
		{
			if(isset($arrSmall[$strCurrentChar]))
			{
				$arrRectangle = $this->__fncDrawRectangle(array('objIssuer'=>$objIssuer,'strStaticPlain'=>'vertical','intLength'=>5,'intWidth'=>8,'objStartingPos'=>$block_pos,'objItem'=>$objItem,'intCurrentDirection'=>$intOppositeDirection));
				$block_pos = $this->__fncCalculateStringPosition($block_pos ,$this->objStartingDirection,7);

				foreach($arrSmall[$strCurrentChar] AS $intCurrentRemoval)
				{
					if(isset($arrRectangle[$intCurrentRemoval]))
					{
						$objIssuer->getLevel()->setBlock($arrRectangle[$intCurrentRemoval], $objBackgroundBlock);
					}
				}
			}
			elseif ($strCurrentChar == ' ')
			{
				$block_pos = $this->__fncCalculateStringPosition($block_pos ,$this->objStartingDirection,5);
			}
		}
		return $this->arrReturnMessage['string'];
	}

	private function __fncCalculateStringPosition($block_pos,$intCurrentDirection, $intCount)
	{
		$current_y = $block_pos->y;
		$current_x = $block_pos->x;
		$current_z = $block_pos->z;

		switch($intCurrentDirection)
		{
			case self::DIR_NORTH:
				$current_z = $current_z + $intCount;
			break;
			case self::DIR_EAST:
				$current_x = $current_x-+ $intCount;
			break;
			case self::DIR_SOUTH:
				$current_z = $current_z - $intCount;
			break;
			case self::DIR_WEST:
				$current_x = $current_x + $intCount;
			break;
		}

		$block_pos = new Vector3($current_x, $current_y, $current_z);
		return $block_pos;
	}

	private function __fncPlay($arrParams,$objIssuer)
	{
		$objCurrentSaved = json_decode($this->arrSavedMacros->get($arrParams[0]),true);

		$this->blnPlaying = 1;
		for($step=0;$step < count($objCurrentSaved);$step++)
		{
			$objCurrentStep = $objCurrentSaved[$step];
			//if first step, start at current position
			if($step == 0)
			{
				$this->commandHandler($objCurrentStep['strCmd'],$objCurrentStep['arrParams'],$objIssuer,$objCurrentStep['strAlias']);
				$intNextX = $objIssuer->getLocation()->x;
				$intNextY = $objIssuer->getLocation()->y;
				$intNextZ = $objIssuer->getLocation()->z;
				$intCurrentDirection = $objIssuer->getDirection();

				//indicate how many positions the direction has changed from when it was recorded. This will be used to adjust all steps.
				$intNeededRotation = $intCurrentDirection - $objCurrentStep['intCurrentDirection'];
			}
			else
			{
				$objPreviousStep = $objCurrentSaved[$step-1];
				$intNextDirection = $objCurrentSaved[$step]['intCurrentDirection'] + $intNeededRotation;

				if ($intNextDirection > 3) $intNextDirection = $intNextDirection - 4;
				elseif ($intNextDirection < 0) $intNextDirection = $intNextDirection + 4;

				$intXDiff = ceil($objCurrentStep['objStartingVector']['x']) - ceil($objPreviousStep['objStartingVector']['x']);
				$intZDiff = ceil($objCurrentStep['objStartingVector']['z']) - ceil($objPreviousStep['objStartingVector']['z']);

				//need to figure out how much the user moved when next statement was ran
				switch($intNeededRotation)
				{
					case 0:
						$intNextX = $intNextX + $intXDiff;
						$intNextZ = $intNextZ + $intZDiff;
					break;
					case 2:
					case -2:
						$intNextX = $intNextX - $intXDiff;
						$intNextZ = $intNextZ - $intZDiff;
					break;

					case 1:
					case -3:
						$intNextX = $intNextX - $intZDiff;
						$intNextZ = $intNextZ + $intXDiff;

					break;
					case -1:
					case 3:
						$intNextX = $intNextX + $intZDiff;
						$intNextZ = $intNextZ - $intXDiff;
					break;
				}

				$intYDiff = ceil($objCurrentStep['objStartingVector']['y']) - ceil($objPreviousStep['objStartingVector']['y']);
				$intNextY = $intNextY + $intYDiff;

				$objNewVector = new Vector3($intNextX, $intNextY, $intNextZ);
				$this->commandHandler($objCurrentStep['strCmd'],$objCurrentStep['arrParams'],$objIssuer,$objCurrentStep['strAlias'],$objNewVector,$intNextDirection);
			}
		}
		$this->blnPlaying = 0;
		return $this->arrReturnMessage['play'];
	}

	private function __fncDrawRectangle($criteria = array())
	{
		//$objIssuer (required) is the person who issues the command
		if (!isset($criteria['objIssuer']))
		{
			return false;
		}

		$arrRectangle = array();
		$objIssuer = (isset($criteria['objIssuer'])) ? $criteria['objIssuer'] : '';
		//$strStaticPlain is the plain on which stays static, can be horizontal or vertical
		$strStaticPlain = (isset($criteria['strStaticPlain'])) ? $criteria['strStaticPlain'] : 'z';
		//$objStartingPos is the vector to begin drawing, defaults to the user's position.
		$objStartingPos = (isset($criteria['objStartingPos'])) ? $criteria['objStartingPos'] : new Vector3($objIssuer->getLocation()->x, $objIssuer->getLocation()->y, $objIssuer->getLocation()->z);
		//$intLength is the Length
		$intLength = (isset($criteria['intLength'])) ? (int) $criteria['intLength'] : $this->arrDefaults->get($objIssuer->getName())['length'];
		//$intWidth is the width
		$intWidth = (isset($criteria['intWidth'])) ? (int) $criteria['intWidth'] : $this->arrDefaults->get($objIssuer->getName())['width'];
		//$objItem is the type of block to use.
		$objItem = (isset($criteria['objItem'])) ? $criteria['objItem'] : $objItem = $objIssuer->getInventory()->getItemInHand();

        if($objItem instanceof ItemBlock) {
            $objBlock = Block::get($objItem->getId(), $objItem->getDamage());
        } else {
            $objItem = Item::get(Item::AIR);
            $objBlock = Block::get($objItem->getId(), $objItem->getDamage());
        }

		//$intCurrentDirection is 0,1,2,3 indicating the direction that the wall needs to be built. default is the way the player is facing.
		$intCurrentDirection = (isset($criteria['intCurrentDirection'])) ? $criteria['intCurrentDirection'] : $this->objStartingDirection;

		$intCurrent_x = $objStartingPos->x;
		$intCurrent_y = $objStartingPos->y;
		$intCurrent_z = $objStartingPos->z;

		$intNewFirstLevel = 0;
		$intNewSecondLevel = 0;

		$arrPositioning = array();

		switch($strStaticPlain)
		{
			case 'horizontal':
				if ($intCurrentDirection == (int) self::DIR_NORTH || $intCurrentDirection == (int) self::DIR_SOUTH)
				{
					$intFirstLevel = $intCurrent_x;
					$intSecondLevel = $intCurrent_z;
					$arrPositioning['x'] = &$intNewFirstLevel;
					$arrPositioning['y'] = $intCurrent_y;
					$arrPositioning['z'] = &$intNewSecondLevel;
				}
				else
				{
					$intFirstLevel = $intCurrent_z;
					$intSecondLevel = $intCurrent_x;
					$arrPositioning['z'] = &$intNewFirstLevel;
					$arrPositioning['y'] = $intCurrent_y;
					$arrPositioning['x'] = &$intNewSecondLevel;
				}
			break;
			case 'vertical':
				if ($intCurrentDirection == (int) self::DIR_EAST || $intCurrentDirection == (int) self::DIR_WEST)
				{
					$intFirstLevel = $intCurrent_z;
					$intSecondLevel = $intCurrent_y;
					$arrPositioning['x'] = $intCurrent_x;
					$arrPositioning['y'] = &$intNewSecondLevel;
					$arrPositioning['z'] = &$intNewFirstLevel;
				}
				else
				{
					$intFirstLevel = $intCurrent_x;
					$intSecondLevel = $intCurrent_y;
					$arrPositioning['z'] = $intCurrent_z;;
					$arrPositioning['y'] = &$intNewSecondLevel;
					$arrPositioning['x'] = &$intNewFirstLevel;
				}
			break;
		}

		for($i = 1; $i <= $intLength; $i++)
		{
			if ($intCurrentDirection == (int)self::DIR_NORTH || $intCurrentDirection == (int)self::DIR_EAST)
			{
				$intNewFirstLevel =  $intFirstLevel + $i;
			}
			else
			{
				$intNewFirstLevel =  $intFirstLevel - $i;
			}

			for($j = 0; $j < $intWidth; $j++)
			{
				if ($strStaticPlain == 'vertical' || $intCurrentDirection == (int)self::DIR_WEST || $intCurrentDirection == (int)self::DIR_NORTH)
				{
					$intNewSecondLevel = $intSecondLevel + $j;
				}
				else
				{
					$intNewSecondLevel = $intSecondLevel - $j;
				}

				$block_pos = new Vector3($arrPositioning['x'], $arrPositioning['y'], $arrPositioning['z']);



				$this->__fncSetRollback($objIssuer,$block_pos);
				$arrRectangle[] = $block_pos;

				$objIssuer->getLevel()->setBlock($block_pos, $objBlock);

			}
		}

		return $arrRectangle;
	}

	private function __fncSetupUserDefaults($objIssuer)
	{

		if ($this->arrDefaults->exists($objIssuer->getName())) return;
        
        //set up the defaults for the new user
        $arrDefaults = array();
        $arrDefaults['block'] = 3;
		$arrDefaults['block_sub'] = 0;
		$arrDefaults['length'] = 5;
		$arrDefaults['width'] = 5;
		$arrDefaults['height'] = 5;
		$arrDefaults['depth'] = 4;
		$arrDefaults['size'] = 5;
		$arrDefaults['elevation'] = 0;
		$arrDefaults['radius'] = 5;
        $this->arrDefaults->set($objIssuer->getName(), $arrDefaults);

        $this->arrDefaults->save();
	}

	private function __fncHelp($strAlias, $strSubCommand = '')
	{
		$strOutput = '';

		switch($strSubCommand)
		{
			case 'copy':
				$strOutput .= "Usage: /$strAlias copy l:5 w:5 h:5 ex:grass,water in:air n:house_copy\n";
				$strOutput .= "Optional params:\n";
				$strOutput .= "(n)ame, (l)ength, (w)idth, (h)eight, (ex)clude, (in)clude, and (e)elevation\n";
				$strOutput .= "Copy an area of blocks and save to a named clip - by default it excludes air blocks\n";
			break;

			case 'paste':
				$strOutput .= "Usage: /$strAlias paste n:house_copy\n";
				$strOutput .= "Optional params:\n";
				$strOutput .= "(n)ame, and (e)elevation\n";
				$strOutput .= "Paste an area of blocks by a named clip\n";
			break;

			case 'measure':
				$strOutput .= "Usage: /$strAlias measure\n";
				$strOutput .= "Measure distance to next block of same type as that in hand\n";
			break;

			case 'undo':
				$strOutput .= "Usage: /$strAlias undo\n";
				$strOutput .= "This command takes no params\n";
				$strOutput .= "It will undo the last /$strAlias command\n";
				$strOutput .= "Currently it can only undo 1 command\n";
			break;

			case 'repeat':
				$strOutput .= "Usage: /$strAlias repeat\n";
				$strOutput .= "This command takes no params\n";
				$strOutput .= "It will repeat the last /$strAlias command\n";
				$strOutput .= "This command can save time\n";
			break;

			case 'pool':
				$strOutput .= "Usage: /$strAlias pool w:10 h:10 d:3\n";
				$strOutput .= "Optional params:\n";
				$strOutput .= "(w)idth, (h)eight, and (d)epth\n";
				$strOutput .= "It will create a pool of water in front of you.\n";
			break;

			case 'lavalake':
				$strOutput .= "Usage: /$strAlias lavalake w:10 h:10 d:3\n";
				$strOutput .= "Optional params:\n";
				$strOutput .= "(w)idth, (h)eight, and (d)epth\n";
				$strOutput .= "It will create a pool of lava in front of you.\n";
			break;

			case 'floor':
				$strOutput .= "Usage: /$strAlias floor l:12 w:12 e:-1\n";
				$strOutput .= "Optional params:\n";
				$strOutput .= "(l)ength, (w)idth, and (e)levation\n";
				$strOutput .= "This will draw a floor in front of you.\n";
				$strOutput .= "e:-1 will draw on the level you are standing.\n";
				$strOutput .= "e:6 will draw a ceiling.\n";
			break;

			case 'groundcover':
				$strOutput .= "Usage: /$strAlias groundcover l:12 w:12\n";
				$strOutput .= "Optional params:\n";
				$strOutput .= "(l)ength, and (w)idth\n";
				$strOutput .= "This will cover the ground in front of you.\n";
			break;

            case 'groundreplace':
                $strOutput .= "Usage: /$strAlias groundreplace l:12 w:12\n";
                $strOutput .= "Optional params:\n";
                $strOutput .= "(l)ength, and (w)idth\n";
                $strOutput .= "This will replace the ground in front of you.\n";
                break;

			case 'wall':
				$strOutput .= "Usage: /$strAlias wall l:8 h:4\n";
				$strOutput .= "Optional params:\n";
				$strOutput .= "(l)ength,(h)eight, and (e)elevation\n";
				$strOutput .= "This will draw a wall in front of you.\n";
				$strOutput .= "Your direction will control the wall direction.\n";
			break;

			case 'box':
				$strOutput .= "Usage: /$strAlias box s:15 \n";
				$strOutput .= "Optional params:\n";
				$strOutput .= "(s)ize, and (e)elevation\n";
				$strOutput .= "This will draw a hollow square box in front of you.\n";
			break;

			case 'cube':
				$strOutput .= "Usage: /$strAlias cube s:5 b:diamond\n";
				$strOutput .= "Optional params:\n";
				$strOutput .= "(s)ize, (e)elevation, and (b)lock,\n";
				$strOutput .= "This will draw a solid square cube in front of you.\n";
			break;

			case 'pyramid':
				$strOutput .= "Usage: /$strAlias pyramid s:40\n";
				$strOutput .= "Optional params:\n";
				$strOutput .= "(s)ize, and (e)elevation\n";
				$strOutput .= "This will draw a solid pyramid in front of you.\n";
			break;

			case 'tomb':
				$strOutput .= "Usage: /$strAlias tomb s:40\n";
				$strOutput .= "Optional params:\n";
				$strOutput .= "(s)ize, and (e)elevation\n";
				$strOutput .= "This will draw a hollow pyramid in front of you.\n";
			break;

			case 'diamond':
				$strOutput .= "Usage: /$strAlias diamond s:20\n";
				$strOutput .= "Optional params:\n";
				$strOutput .= "(s)ize, and (e)elevation\n";
				$strOutput .= "This will draw a solid diamond in front of you.\n";
			break;

			case 'sphere':
				$strOutput .= "Usage: /$strAlias sphere r:15 a:t\n";
				$strOutput .= "Optional params:\n";
				$strOutput .= "(r)adius, (e)elevation, and (a)roundme\n";
				$strOutput .= "This will draw a solid sphere in front of you.\n";
			break;

			case 'bubble':
				$strOutput .= "Usage: /$strAlias bubble r:15\n";
				$strOutput .= "Optional params:\n";
				$strOutput .= "(r)adius, and (e)elevation\n";
				$strOutput .= "This will draw a hollow bubble around you.\n";
			break;

			case 'volcano':
				$strOutput .= "Usage: /$strAlias volcano r:15\n";
				$strOutput .= "Optional params:\n";
				$strOutput .= "(r)adius, and (e)elevation\n";
				$strOutput .= "This will draw a hollow volcano around you.\n";
			break;

			case 'steps':
				$strOutput .= "Usage: /$strAlias steps s:40\n";
				$strOutput .= "Optional params:\n";
				$strOutput .= "(h)eight, (w)idth, and (e)elevation\n";
				$strOutput .= "This will draw a steps in front of you.\n";
			break;

			case 'write':
				$strOutput .= "Usage: /$strAlias write t:test message\n";
				$strOutput .= "Optional params:\n";
				$strOutput .= "(t)ext, and (e)elevation\n";
				$strOutput .= "This will write a block message for you.\n";
				$strOutput .= "Each letter is 8 blocks height and 5 blocks wide.\n";
			break;

			case 'prism':
				$strOutput .= "Usage: /$strAlias prism w:10 h:5 l:15 e:5\n";
				$strOutput .= "Optional params:\n";
				$strOutput .= "(l)ength, (w)idth, (h)eight, and (e)elevation\n";
				$strOutput .= "This will draw a rectangle prism with the given dimensions.\n";
			break;

			case 'cut':
				$strOutput .= "Usage: /$strAlias cut w:10 h:5 l:15 e:5\n";
				$strOutput .= "Optional params:\n";
				$strOutput .= "(l)ength, (w)idth, (h)eight, and (e)elevation\n";
				$strOutput .= "This will replace given rectangle prism with air.\n";
			break;

			case 'record':
				$strOutput .= "Usage: /$strAlias record start|save|cancel|delete\n";
				$strOutput .= "Allows user to save and /draw commands:\n";
				$strOutput .= "Save and Delete will also need a name param.\n";
			break;

			case 'play':
				$strOutput .= "Usage: /$strAlias play house_shell\n";
				$strOutput .= "Play a saved named macro. Used for repeating things over and over.\n";
			break;

			case 'set':
				$strOutput .= "Usage: /$strAlias set w:10\n";
				$strOutput .= "Allows you to change the defaults values.\n";
				$strOutput .= "Possible defaults:\n";
				$strOutput .= "height, width, length, elevation, size, depth, radius\n";
			break;

			default:
				$strOutput .= "Usage: /$strAlias <command> [parameters...]\n";
				$strOutput .= "Possible commands:\n";
				$strOutput .= "floor, wall, pool, box, cube, pyramid, diamond, steps, sphere, write, prism, cut, repeat, undo, record, play, set\n";
				$strOutput .= "/$strAlias <command> help for more details.\n";
		}

		return $strOutput;
	}

	public function __destruct(){}
}
?>