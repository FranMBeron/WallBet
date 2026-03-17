<?php

namespace App\Enums;

enum LeagueStatus: string
{
    case Upcoming = 'upcoming';
    case Active = 'active';
    case Finished = 'finished';
}
