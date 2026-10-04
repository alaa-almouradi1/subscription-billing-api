<?php

namespace App\Domain\Payments;

use RuntimeException;

final class GatewayUnavailable extends RuntimeException {}
