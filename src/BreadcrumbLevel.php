<?php

declare(strict_types=1);

namespace Oluso;

enum BreadcrumbLevel: string
{
    case Debug = 'debug';
    case Info = 'info';
    case Warning = 'warning';
    case Error = 'error';
}
