<?php

namespace Che\SimpleLisp;

enum Procedure: string
{
    case COND = 'cond';
    case DEF = 'def';
    case SET = 'set!';
    case DO = 'do';
    case QUOTE = 'quote';
    case LAMBDA = 'lambda';
    case MACRO = 'macro';
    case EVAL = 'eval';

    case EQUAL = '=';
    case GT = '>';
    case LT = '<';
    case GTOEQ = '>=';
    case LTOEQ = '<=';
    case NOT = 'not';
    case ABS = 'abs';
    case SUM = '+';
    case SUB = '-';
    case MUL = '*';
    case DIV = '/';
    case MAX = 'max';
    case MIN = 'min';
    case MOD = 'mod';
    case CONCAT = '++';
    case PRINT = 'print';
    case CAR = 'car';
    case CDR = 'cdr';
    case CONS = 'cons';
}
