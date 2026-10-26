<?php

declare(strict_types=1);

namespace WilyCode\Form;

enum FormMethod: string
{
    case Post = 'POST';
    case Get = 'GET';
    case Put = 'PUT';
    case Delete = 'DELETE';
    case Patch = 'PATCH';
}
