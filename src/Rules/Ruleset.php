<?php

declare(strict_types=1);

namespace hkyss\Tune\Rules;

final class Ruleset
{
    /** @return list<Rule> */
    public static function evolutionCore(): array
    {
        return array_merge(
            self::tree(),
            self::elements(),
            self::elementNames(),
            self::access(),
            self::content(),
            self::logs()
        );
    }

    /** @return list<Rule> */
    private static function tree(): array
    {
        return [
            Rule::addUnique(
                'closure.pair',
                'site_content_closure',
                'tune_closure_pair',
                ['ancestor', 'descendant'],
                Tier::Core,
                'The closure table ships with a primary key on closure_id and nothing else, so every '
                . 'subtree query scans it whole and a duplicated pair multiplies the result silently.'
            ),
            Rule::addIndex(
                'closure.descendant_depth',
                'site_content_closure',
                'tune_closure_descendant_depth',
                ['descendant', 'depth'],
                Tier::Core,
                'Walking up from a document — breadcrumbs, inherited permissions — reads the closure '
                . 'table by descendant, which no shipped index leads with.'
            ),
            Rule::addIndex(
                'closure.ancestor_depth',
                'site_content_closure',
                'tune_closure_ancestor_depth',
                ['ancestor', 'depth'],
                Tier::Extended,
                'Only pays off where subtrees are read one level at a time; the unique pair already '
                . 'covers the unfiltered descent.'
            ),
        ];
    }

    /** @return list<Rule> */
    private static function elements(): array
    {
        return [
            Rule::addIndex(
                'tmplvar_templates.template',
                'site_tmplvar_templates',
                'tune_tvtpl_template',
                ['templateid', 'rank'],
                Tier::Core,
                'The primary key starts at tmplvarid, so asking which template variables a template '
                . 'has — once per render and once per edit form — cannot use it.'
            ),
            Rule::addUnique(
                'tmplvar_contentvalues.pair',
                'site_tmplvar_contentvalues',
                'tune_tvcv_pair',
                ['tmplvarid', 'contentid'],
                Tier::Core,
                'One value per template variable per document. user_values enforces this on the user '
                . 'side; the document side never did, which is where duplicate rows come from.',
                'idx_tmplvarid_contentid'
            ),
            Rule::dropIndex(
                'tmplvar_contentvalues.value_only',
                'site_tmplvar_contentvalues',
                'idx_value_prefix',
                Tier::Extended,
                'Matches a template variable value without saying which variable — a query Evolution '
                . 'does not make — on the busiest write path in the schema.'
            ),
            Rule::dropIndex(
                'tmplvar_contentvalues.triple',
                'site_tmplvar_contentvalues',
                'idx_tmplvarid_contentid_value_prefix',
                Tier::Extended,
                'Superseded by the unique pair and by idx_tmplvarid_value_prefix; on its own it only '
                . 'widens every write.'
            ),
            Rule::dropIndex(
                'tmplvar_contentvalues.fulltext',
                'site_tmplvar_contentvalues',
                'ft_value',
                Tier::Aggressive,
                'A fulltext index over a mediumtext column on the table that changes most. Drop it '
                . 'only where nothing searches template variable values.',
                true
            ),
        ];
    }

    /** @return list<Rule> */
    private static function elementNames(): array
    {
        $reason = 'Elements are found by name. The site cache answers that at runtime, so what an '
            . 'index here pays for is rebuilding the cache and the lists in the manager.';

        $rules = [];

        foreach ([
            'snippets' => ['site_snippets', 'name'],
            'chunks' => ['site_htmlsnippets', 'name'],
            'plugins' => ['site_plugins', 'name'],
            'modules' => ['site_modules', 'name'],
            'templates' => ['site_templates', 'templatename'],
            'tmplvars' => ['site_tmplvars', 'name'],
        ] as $key => [$table, $column]) {
            $rules[] = Rule::addIndex(
                sprintf('%s.name', $key),
                $table,
                sprintf('tune_%s_name', $key),
                [$column],
                Tier::Extended,
                $reason
            );
        }

        return $rules;
    }

    /** @return list<Rule> */
    private static function access(): array
    {
        return [
            Rule::addIndex(
                'membergroup_access.documentgroup',
                'membergroup_access',
                'tune_mga_documentgroup',
                ['documentgroup', 'membergroup'],
                Tier::Core,
                'Web user permission checks join this table on every request and it ships with no '
                . 'index but the primary key.'
            ),
            Rule::addIndex(
                'membergroup_access.membergroup',
                'membergroup_access',
                'tune_mga_membergroup',
                ['membergroup', 'documentgroup'],
                Tier::Extended,
                'The manager reads the same table the other way round when it edits a member group.'
            ),
            Rule::addIndex(
                'tmplvar_access.documentgroup',
                'site_tmplvar_access',
                'tune_tva_documentgroup',
                ['documentgroup', 'tmplvarid'],
                Tier::Core,
                'Same shape as membergroup_access, same missing index, and it gates every template '
                . 'variable a restricted user can see.'
            ),
            Rule::addIndex(
                'tmplvar_access.tmplvarid',
                'site_tmplvar_access',
                'tune_tva_tmplvarid',
                ['tmplvarid'],
                Tier::Extended,
                'Reached only from the template variable edit form.'
            ),
            Rule::addIndex(
                'active_user_sessions.lasthit',
                'active_user_sessions',
                'tune_aus_lasthit',
                ['lasthit'],
                Tier::Core,
                'Stale sessions are swept by lasthit. Without an index the sweep scans the session '
                . 'table, and it runs on the hit that triggers it.'
            ),
            Rule::addIndex(
                'active_user_sessions.internalkey',
                'active_user_sessions',
                'tune_aus_internalkey',
                ['internalKey'],
                Tier::Extended,
                'Finding a user\'s own session, which the manager does once per login.'
            ),
            Rule::addIndex(
                'active_users.lasthit',
                'active_users',
                'tune_au_lasthit',
                ['lasthit'],
                Tier::Core,
                'The who-is-online list is swept by lasthit and read again on every manager page, '
                . 'and the primary key starts at the session id, so neither can use it.'
            ),
            Rule::addIndex(
                'active_users.internalkey',
                'active_users',
                'tune_au_internalkey',
                ['internalKey'],
                Tier::Extended,
                'Same table from the other side: finding the row belonging to one user means a '
                . 'scan, because the primary key leads with the session id.'
            ),
            Rule::addIndex(
                'plugin_events.event',
                'site_plugin_events',
                'tune_spe_event',
                ['evtid', 'pluginid'],
                Tier::Extended,
                'Which plugins answer an event is asked with the event in hand, and the primary '
                . 'key starts at the plugin. The map is cached, so this pays for rebuilding it.'
            ),
            Rule::addIndex(
                'user_role_vars.role',
                'user_role_vars',
                'tune_urv_role',
                ['roleid', 'rank'],
                Tier::Extended,
                'The same shape as the template side of template variables: the primary key leads '
                . 'with the variable, and the question asked is which ones a role may see.'
            ),
            Rule::addIndex(
                'module_access.module',
                'site_module_access',
                'tune_sma_module',
                ['module', 'usergroup'],
                Tier::Extended,
                'The module permission check reads by module, and this table carries nothing but '
                . 'its primary key.'
            ),
            Rule::addIndex(
                'module_depobj.module',
                'site_module_depobj',
                'tune_smd_module',
                ['module', 'resource'],
                Tier::Extended,
                'Module dependencies are read by module, and this table carries nothing either.'
            ),
            Rule::addIndex(
                'user_attributes.email',
                'user_attributes',
                'tune_user_email',
                ['email'],
                Tier::Core,
                'Login, registration and password recovery all look a user up by email, and the '
                . 'column carries no index at all.'
            ),
        ];
    }

    /** @return list<Rule> */
    private static function content(): array
    {
        return [
            Rule::addIndex(
                'site_content.children',
                'site_content',
                'tune_content_children',
                ['parent', 'deleted', 'published', 'menuindex'],
                Tier::Core,
                'The query behind every menu and listing filters on parent, deleted and published '
                . 'and orders by menuindex. The shipped index on parent alone leaves the rest to a '
                . 'row-by-row filter and a filesort.'
            ),
            Rule::addIndex(
                'site_content.alias_parent',
                'site_content',
                'tune_content_alias_parent',
                ['alias', 'parent'],
                Tier::Core,
                'Friendly URL resolution matches an alias within a parent, one segment at a time.'
            ),
            Rule::addIndex(
                'site_content.template',
                'site_content',
                'tune_content_template',
                ['template'],
                Tier::Extended,
                'Listing the documents of a template, and the cascade when a template is changed, '
                . 'both scan site_content today.'
            ),
            Rule::dropIndex(
                'site_content.fulltext',
                'site_content',
                'content_ft_idx',
                Tier::Aggressive,
                'InnoDB maintains this over a longtext column on every document save. Drop it only '
                . 'where the built-in search is unused; dropping the last fulltext index on a table '
                . 'rebuilds that table.',
                true
            ),
        ];
    }

    /** @return list<Rule> */
    private static function logs(): array
    {
        return [
            Rule::addIndex(
                'event_log.createdon',
                'event_log',
                'tune_event_log_createdon',
                ['createdon'],
                Tier::Core,
                'The manager lists the event log newest first, so every page of it is a filesort '
                . 'over the whole table.'
            ),
            Rule::dropIndex(
                'manager_log.message',
                'manager_log',
                'manager_log_message_index',
                Tier::Extended,
                'A 255-character index on an append-only audit log, over a column the manager offers '
                . 'no way to filter by.'
            ),
            Rule::dropIndex(
                'manager_log.itemname',
                'manager_log',
                'manager_log_itemname_index',
                Tier::Extended,
                'Same table, same shape, same absent query.'
            ),
        ];
    }
}
