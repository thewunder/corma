<?php
namespace Corma\DataObject\Identifier;

use Corma\Exception\InvalidArgumentException;

/**
 * Allows for a customizable identifier column via the #[IdColumn("custom_id")] attribute
 */
abstract class CustomizableIdentifier extends BaseIdentifier
{
    private ?string $idColumn = null;

    public function getIdColumn($objectOrClass): string
    {
        if ($this->idColumn) {
            return $this->idColumn;
        }

        $class = new \ReflectionClass($objectOrClass);
        $attributes = $class->getAttributes(IdColumn::class);
        if (!empty($attributes)) {
            if (count($attributes) > 1) {
                throw new InvalidArgumentException('Only one IdColumn attribute allowed');
            }

            /** @var IdColumn $idColumn */
            $idColumn = $attributes[0]->newInstance();
            return $this->idColumn = $idColumn->getColumn();
        }

        return $this->idColumn = parent::getIdColumn($objectOrClass);
    }
}
