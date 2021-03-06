<?php
/*!
 * Traq
 * Copyright (C) 2009-2014 Jack Polgar
 * Copyright (C) 2012-2014 Traq.io
 * https://github.com/nirix
 * http://traq.io
 *
 * This file is part of Traq.
 *
 * Traq is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; version 3 only.
 *
 * Traq is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Traq. If not, see <http://www.gnu.org/licenses/>.
 */

namespace Traq\Helpers;

use Avalon\Database\QueryBuilder;
use Traq\Models\Project;
use Traq\Models\Milestone;
use Traq\Models\Status;
use Traq\Models\Type;
use Traq\Models\Ticket;
use Traq\Models\User;
use Traq\Models\Component;
use Traq\Models\Priority;
use Traq\Models\Severity;
use Traq\Models\CustomField;

/**
 * Ticket filter query builder.
 *
 * @author Jack P.
 * @since 3.0
 */
class TicketFilterQuery
{
    /**
     * @var Project
     */
    protected $project;

    /**
     * @var QueryBuilder
     */
    protected $builder;

    /**
     * @var array
     */
    protected $filters;

    public function __construct(Project $project)
    {
        $this->project = $project;
        $this->builder = $project->tickets();
    }

    /**
     * Processes a filter.
     *
     * @param string $filter
     * @param array $values
     */
    public function process($filter, $values)
    {
        if ($filter == 'search') {
            $values = is_array($values) ? $values : [$values];
        } elseif (!is_array($values)) {
            $values = explode(',', $values);
        }

        $condition = '';
        if (substr($values[0], 0, 1) == '!') {
            $condition = 'NOT';
            $values[0] = substr($values[0], 1);
        }

        // Add to filters array
        $this->filters[$filter] = [
            'prefix' => ($condition == 'NOT' ? '!' :''),
            'values' => []
        ];

        if ($values[0] == '' or end($values) == '') {
            $this->filters[$filter]['values'][] = '';
        }

        if (count($values)) {
            $this->add($filter, $condition, $values);
        }
    }

    /**
     * Checks the values and constructs the query.
     *
     * @param string $filter
     * @param string $condition
     * @param array  $values
     */
    protected function add($filter, $condition, $values)
    {
        $queryValues = [];

        if (!count($values)) {
            return false;
        }

        switch($filter) {
            case 'milestone':
                $values = $this->filterMilestone($condition, $values);
                break;

            case 'version':
                $values = $this->filterMilestone($condition, $values, 'version_id');
                break;

            case 'status':
                $values = $this->filterStatus($condition, $values);
                break;

            case 'type':
                $values = $this->filterType($condition, $values);
                break;

            case 'component':
                $values = $this->filterComponent($condition, $values);
                break;

            case 'priority':
                $values = $this->filterComponent($condition, $values);
                break;

            case 'severity':
                $values = $this->filterSeverity($condition, $values);
                break;

            case 'summary':
                $values = $this->filterSummary($condition, $values);
                break;

            case 'description':
                $values = $this->filterDescription($condition, $values);
                break;

            case 'owner':
                $values = $this->filterOwner($condition, $values);
                break;

            case 'assigned_to':
                $values = $this->filterAssignedTo($condition, $values);
                break;

            case 'search':
                $values = $this->filterSummary($condition, $values);
                $this->filterDescription($condition, $values);
                break;
        }

        $this->filters[$filter]['values'] = $values;
    }

    /**
     * Process milestone filter.
     *
     * @param string $condition
     * @param array  $values
     * @param string $field     Filter by `milestone_id` or `version_id`
     *
     * @return array
     */
    protected function filterMilestone($condition, $values, $field = 'milestone_id')
    {
        $ids = [];

        foreach ($values as $value) {
            if ($milestone = Milestone::find('slug', $value)) {
                $ids[] = $milestone->id;
            }
        }

        if (count($ids)) {
            if ($condition == 'NOT') {
                $in = $this->builder->expr()->notIn($field, $ids);
            } else {
                $in = $this->builder->expr()->in($field, $ids);
            }

            $this->builder->andWhere($in);
        }

        return $ids;
    }

    /**
     * Process status filter.
     *
     * @param string $condition
     * @param array  $values
     *
     * @return array
     */
    protected function filterStatus($condition, $values)
    {
        $ids = [];

        // All open statuses
        if ($values[0] == 'all.open') {
            foreach (Status::allOpen() as $status) {
                $ids[] = $status->id;
            }
        }
        // All closed statuses
        elseif ($values[0] == 'all.closed') {
            foreach (Status::allClosed() as $status) {
                $ids[] = $status->id;
            }
        }
        // Statuses from request
        else {
            foreach ($values as $value) {
                if ($status = Status::find('name', $value)) {
                    $ids[] = $status->id;
                }
            }
        }

        if (count($ids)) {
            if ($condition == 'NOT') {
                $in = $this->builder->expr()->notIn('status_id', $ids);
            } else {
                $in = $this->builder->expr()->in('status_id', $ids);
            }

            $this->builder->andWhere($in);
        }

        return $ids;
    }

    /**
     * Process ticket type filter.
     *
     * @param string $condition
     * @param array  $values
     *
     * @return array
     */
    protected function filterType($condition, $values)
    {
        $ids = [];

        foreach ($values as $value) {
            if ($type = Type::find('name', $value)) {
                $ids[] = $type->id;
            }
        }

        if (count($ids)) {
            if ($condition == 'NOT') {
                $in = $this->builder->expr()->notIn('type_id', $ids);
            } else {
                $in = $this->builder->expr()->in('type_id', $ids);
            }

            $this->builder->andWhere($in);
        }

        return $ids;
    }

    /**
     * Process ticket component filter.
     *
     * @param string $condition
     * @param array  $values
     *
     * @return array
     */
    protected function filterComponent($condition, $values)
    {
        $ids = [];

        foreach ($values as $value) {
            if ($component = Component::find('name', $value)) {
                $ids[] = $component->id;
            }
        }

        if (count($ids)) {
            if ($condition == 'NOT') {
                $in = $this->builder->expr()->notIn('component_id', $ids);
            } else {
                $in = $this->builder->expr()->in('component_id', $ids);
            }

            $this->builder->andWhere($in);
        }

        return $ids;
    }

    /**
     * Process ticket priority filter.
     *
     * @param string $condition
     * @param array  $values
     *
     * @return array
     */
    protected function filterPriority($condition, $values)
    {
        $ids = [];

        foreach ($values as $value) {
            if ($priority = Priority::find('name', $value)) {
                $ids[] = $priority->id;
            }
        }

        if (count($ids)) {
            if ($condition == 'NOT') {
                $in = $this->builder->expr()->notIn('priority_id', $ids);
            } else {
                $in = $this->builder->expr()->in('priority_id', $ids);
            }

            $this->builder->andWhere($in);
        }

        return $ids;
    }

    /**
     * Process ticket severity filter.
     *
     * @param string $condition
     * @param array  $values
     *
     * @return array
     */
    protected function filterSeverity($condition, $values)
    {
        $ids = [];

        foreach ($values as $value) {
            if ($severity = Severity::find('name', $value)) {
                $ids[] = $severity->id;
            }
        }

        if (count($ids)) {
            if ($condition == 'NOT') {
                $in = $this->builder->expr()->notIn('severity_id', $ids);
            } else {
                $in = $this->builder->expr()->in('severity_id', $ids);
            }

            $this->builder->andWhere($in);
        }

        return $ids;
    }

    /**
     * Process ticket summary filter.
     *
     * @param string $condition
     * @param array  $values
     *
     * @return array
     */
    protected function filterSummary($condition, $values)
    {
        $conditions = [];

        foreach ($values as $value) {
            $value = str_replace('*', '%', $value);

            if ($value !== '') {
                if ($condition == 'NOT') {
                    $conditions[] = $this->builder()->expr()->notLike('summary', "'%{$value}%'");
                } else {
                    $conditions[] = $this->builder()->expr()->like('summary', "'%{$value}%'");
                }
            }
        }

        if (count($conditions)) {
            $orX = call_user_func_array([$this->builder()->expr(), 'orX'], $conditions);
            $this->builder()->andWhere($orX);
        }

        return $values;
    }

    /**
     * Process ticket description filter.
     *
     * @param string $condition
     * @param array  $values
     *
     * @return array
     */
    protected function filterDescription($condition, $values)
    {
        $conditions = [];

        foreach ($values as $value) {
            $value = str_replace('*', '%', $value);

            if ($value !== '') {
                if ($condition == 'NOT') {
                    $conditions[] = $this->builder()->expr()->notLike(
                        'body',
                        $this->builder->getConnection()->quote("%{$value}%")
                    );
                } else {
                    $conditions[] = $this->builder()->expr()->like(
                        'body',
                        $this->builder->getConnection()->quote("%{$value}%")
                    );
                }
            }
        }

        if (count($conditions)) {
            $orX = call_user_func_array([$this->builder()->expr(), 'orX'], $conditions);
            $this->builder()->andWhere($orX);
        }

        return $values;
    }

    /**
     * Process ticket owner filter.
     *
     * @param string $condition
     * @param array  $values
     *
     * @return array
     */
    protected function filterOwner($condition, $values)
    {
        $ids = [];

        foreach ($values as $value) {
            if ($value !== '' && $user = User::find('name', $value)) {
                $ids[] = $user->id;
            }
        }

        if (count($ids)) {
            if ($condition == 'NOT') {
                $in = $this->builder->expr()->notIn('user_id', $ids);
            } else {
                $in = $this->builder->expr()->in('user_id', $ids);
            }

            $this->builder->andWhere($in);
        }

        return $values;
    }

    /**
     * Process ticket assignee filter.
     *
     * @param string $condition
     * @param array  $values
     *
     * @return array
     */
    protected function filterAssignedTo($condition, $values)
    {
        $ids = [];

        foreach ($values as $value) {
            if ($value !== '' && $user = User::find('name', $value)) {
                $ids[] = $user->id;
            }
        }

        if (count($ids)) {
            if ($condition == 'NOT') {
                $in = $this->builder->expr()->notIn('assigned_to_id', $ids);
            } else {
                $in = $this->builder->expr()->in('assigned_to_id', $ids);
            }

            $this->builder->andWhere($in);
        }

        return $values;
    }

    protected function filterOwnerWIP($condition, $values)
    {
        $conditions = [];

        $this->builder->join('tickets', 'users', 'owner', 'owner.id = tickets.user_id');

        foreach ($values as $value) {
            if ($value !== '') {
                if ($condition == 'NOT') {
                    $conditions[] = $this->builder()->expr()->neq(
                        'owner.name',
                        $this->builder->getConnection()->quote("{$value}")
                    );
                } else {
                    $conditions[] = $this->builder()->expr()->eq(
                        'owner.name',
                        $this->builder->getConnection()->quote("{$value}")
                    );
                }
            }
        }

        if (count($conditions)) {
            $orX = call_user_func_array([$this->builder()->expr(), 'orX'], $conditions);
            $this->builder()->andWhere($orX);
        }

        return $values;
    }

    /**
     * Returns filters.
     *
     * @return array
     */
    public function filters()
    {
        return $this->filters;
    }

    /**
     * Returns the query builder.
     *
     * @return QueryBuilder
     */
    public function builder()
    {
        return $this->builder;
    }
}
