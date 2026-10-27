<?php

namespace App\Enums;

enum LetterDraftKindEnum: int
{
    case INITIAL = 1;
    case REVISION = 2;
    case RECOVERY = 3;
}
