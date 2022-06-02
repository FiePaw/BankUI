<?php

namespace FiePaw\BankUI;

use Cassandra\Time;
use FiePaw\BankUI\BankUI;

use pocketmine\scheduler\Task;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\utils\Config;

class InterestTask extends Task{

    public function __construct(BankUI $plugin){
        $this->plugin = $plugin;
    }

    public function onRun(): void
    {

        $this->plugin->dailyInterest();

    }
}