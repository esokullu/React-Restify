<?php
/**
 * @author Klyachin Andrey <akliachin@kiwitaxi.com>
 */

namespace CapMousse\ReactRestify\Container;

use Psr\Container\NotFoundExceptionInterface;

class NotFoundException extends \Exception implements NotFoundExceptionInterface
{
}
