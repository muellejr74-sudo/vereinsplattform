<?php

namespace App\Modules\Kern\Mandanten;

use LogicException;

/** Verstoß gegen die Mandantentrennung – ein Programmierfehler, kein Benutzerfehler. */
class MandantFehlt extends LogicException {}
