<?php

declare(strict_types=1);

namespace hkyss\Tune\Analysis;

use hkyss\Tune\Schema\SchemaReader;

final class TypeReport
{
    private const REFERENCES = [
        ['site_content', 'parent', 'site_content', 'id'],
        ['site_content', 'template', 'site_templates', 'id'],
        ['site_content', 'createdby', 'users', 'id'],
        ['site_content', 'editedby', 'users', 'id'],
        ['site_tmplvar_contentvalues', 'contentid', 'site_content', 'id'],
        ['site_tmplvar_contentvalues', 'tmplvarid', 'site_tmplvars', 'id'],
        ['site_tmplvar_templates', 'templateid', 'site_templates', 'id'],
        ['document_groups', 'document', 'site_content', 'id'],
    ];

    /** @return list<TypeMismatch> */
    public function against(SchemaReader $reader): array
    {
        $found = [];

        foreach (self::REFERENCES as [$table, $column, $target, $targetColumn]) {
            $type = $reader->columnType($table, $column);
            $targetType = $reader->columnType($target, $targetColumn);

            if ($type === null || $targetType === null || $type === $targetType) {
                continue;
            }

            $found[] = new TypeMismatch(
                $table,
                $column,
                $type,
                sprintf('%s.%s', $target, $targetColumn),
                $targetType
            );
        }

        return $found;
    }
}
