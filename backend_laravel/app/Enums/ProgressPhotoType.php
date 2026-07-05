<?php

namespace App\Enums;

enum ProgressPhotoType: string
{
    case Front = 'front';
    case Side = 'side';
    case Back = 'back';
    case Other = 'other';
}
