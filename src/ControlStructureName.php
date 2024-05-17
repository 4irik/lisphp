<?php

declare(strict_types=1);

namespace Che\SimpleLisp;

enum ControlStructureName: string
{
    case COND = 'cond';
    case DEF = 'def';
    case SET = 'set!';
    case DO = 'do';
    case QUOTE = 'quote';
    case LAMBDA = 'lambda';
    case MACRO = 'macro';
    case EVAL = 'eval';
    case TYPEOF = 'typeof';
    case PRINT = 'print';
    case SYMBOL = 'symbol'; // todo: realize
    case EVAL_IN = 'eval-in'; // todo: realize
}
