<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The predefined avatars a user can pick for their account (no photo uploads — owner
 * decision 2026-10-31). The client maps each case to its emoji; the server only stores
 * and validates the key. Mirrored in the mobile app as `src/constants/avatars.ts`.
 */
enum Avatar: string
{
    case Fox = 'fox';
    case Panda = 'panda';
    case Tiger = 'tiger';
    case Lion = 'lion';
    case Frog = 'frog';
    case Koala = 'koala';
    case Unicorn = 'unicorn';
    case Octopus = 'octopus';
    case Penguin = 'penguin';
    case Owl = 'owl';
    case Turtle = 'turtle';
    case Dolphin = 'dolphin';
    case Butterfly = 'butterfly';
    case Bee = 'bee';
    case Rocket = 'rocket';
    case Star = 'star';
}
