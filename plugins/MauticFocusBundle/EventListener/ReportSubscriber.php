<?php

declare(strict_types=1);

namespace MauticPlugin\MauticFocusBundle\EventListener;

use Mautic\ReportBundle\Event\ReportBuilderEvent;
use Mautic\ReportBundle\Event\ReportGeneratorEvent;
use Mautic\ReportBundle\ReportEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class ReportSubscriber implements EventSubscriberInterface
{
    public const CONTEXT_FOCUS_STATS = 'focus_stats';
    public const CONTEXT_FOCUS_LEADS = 'focus_leads';
    public const FOCUS_GROUP         = 'focus';
    public const PREFIX_FOCUS        = 'f';
    public const PREFIX_STATS        = 'fs';
    public const PREFIX_REDIRECTS    = 'r';
    public const PREFIX_TRACKABLES   = 't';
    public const PREFIX_CATEGORIES   = 'c';
    public const PREFIX_LEADS        = 'l';

    public static function getSubscribedEvents(): array
    {
        return [
            ReportEvents::REPORT_ON_BUILD    => ['onReportBuilder', 0],
            ReportEvents::REPORT_ON_GENERATE => ['onReportGenerate', 0],
        ];
    }

    /**
     * Add available tables and columns to the report builder lookup.
     */
    public function onReportBuilder(ReportBuilderEvent $event): void
    {
        if (!$event->checkContext([self::CONTEXT_FOCUS_LEADS, self::CONTEXT_FOCUS_STATS])) {
            return;
        }

        $columns = [
            self::PREFIX_FOCUS.'.id' => [
                'label'   => 'mautic.report.focus.id',
                'type'    => 'html',
                'alias'   => 'focus_id',
                'formula' => 'MAX('.self::PREFIX_FOCUS.'.id)',
            ],
            self::PREFIX_FOCUS.'.name' => [
                'label'   => 'mautic.report.focus.name',
                'type'    => 'html',
                'alias'   => 'focus_name',
                'formula' => 'MAX('.self::PREFIX_FOCUS.'.name)',
            ],
            self::PREFIX_FOCUS.'.category' => [
                'label'   => 'mautic.report.focus.category',
                'type'    => 'html',
                'alias'   => 'category_name',
                'formula' => 'MAX('.self::PREFIX_CATEGORIES.'.title)',
            ],
            self::PREFIX_FOCUS.'.description' => [
                'label'   => 'mautic.report.focus.description',
                'type'    => 'html',
                'alias'   => 'focus_desc',
                'formula' => 'MAX('.self::PREFIX_FOCUS.'.description)',
            ],
            self::PREFIX_FOCUS.'.focus_type' => [
                'label'   => 'mautic.focus.thead.type',
                'type'    => 'html',
                'alias'   => 'focus_type',
                'formula' => 'MAX('.self::PREFIX_FOCUS.'.focus_type)',
            ],
            self::PREFIX_FOCUS.'.style' => [
                'label'   => 'mautic.report.focus.style',
                'type'    => 'html',
                'alias'   => 'focus_style',
                'formula' => 'MAX('.self::PREFIX_FOCUS.'.style)',
            ],
            self::PREFIX_STATS.'.type' => [
                'label' => 'mautic.focus.interaction',
                'type'  => 'html',
                'alias' => 'interaction_type',
            ],
            self::PREFIX_TRACKABLES.'.hits' => [
                'label'   => 'mautic.report.focus.hits',
                'type'    => 'html',
                'alias'   => 'hit_count',
                'formula' => 'CASE 
                    WHEN '.self::PREFIX_STATS.'.type = "view" THEN (
                        SELECT COUNT(fs2.id) 
                        FROM '.MAUTIC_TABLE_PREFIX.'focus_stats fs2                         
                        WHERE fs2.type = "view" 
                        AND fs2.focus_id = '.self::PREFIX_STATS.'.focus_id
                        GROUP BY fs2.focus_id
                    )
                    WHEN '.self::PREFIX_STATS.'.type = "submission" THEN (
                        SELECT COUNT(fs2.id) 
                        FROM '.MAUTIC_TABLE_PREFIX.'focus_stats fs2                          
                        WHERE fs2.type = "submission" 
                        AND fs2.focus_id = '.self::PREFIX_STATS.'.focus_id
                        GROUP BY fs2.focus_id
                    )
                    ELSE MAX('.self::PREFIX_TRACKABLES.'.hits)
                END',
            ],
            self::PREFIX_TRACKABLES.'.unique_hits' => [
                'label'   => 'mautic.report.focus.uniquehits',
                'type'    => 'html',
                'alias'   => 'unique_hit_count',
                'formula' => 'CASE 
                    WHEN '.self::PREFIX_STATS.'.type = "view" THEN (
                        SELECT COUNT(DISTINCT fs2.lead_id) 
                        FROM '.MAUTIC_TABLE_PREFIX.'focus_stats fs2 
                        WHERE fs2.type = "view" 
                        AND fs2.focus_id = '.self::PREFIX_STATS.'.focus_id
                    )
                    WHEN '.self::PREFIX_STATS.'.type = "submission" THEN (
                        SELECT COUNT(DISTINCT fs2.lead_id) 
                        FROM '.MAUTIC_TABLE_PREFIX.'focus_stats fs2 
                        WHERE fs2.type = "submission" 
                        AND fs2.focus_id = '.self::PREFIX_STATS.'.focus_id
                    )
                    ELSE MAX('.self::PREFIX_TRACKABLES.'.unique_hits)
                END',
            ],
            self::PREFIX_STATS.'.conversion_rate_submission' => [
                'label'   => 'mautic.report.focus.ratio.submission',
                'type'    => 'html',
                'alias'   => 'conversion_rate_submission',
                'formula' => 'CASE
                    WHEN '.self::PREFIX_STATS.'.type = "submission" THEN (
                        SELECT
                            ROUND(
                                (
                                    SELECT COUNT(fs2.id)
                                    FROM '.MAUTIC_TABLE_PREFIX.'focus_stats fs2
                                    WHERE fs2.type = "submission"
                                    AND fs2.focus_id = '.self::PREFIX_STATS.'.focus_id
                                ) * 100.0 /
                                NULLIF((
                                    SELECT COUNT(fs3.id)
                                    FROM '.MAUTIC_TABLE_PREFIX.'focus_stats fs3
                                    WHERE fs3.type = "view"
                                    AND fs3.focus_id = '.self::PREFIX_STATS.'.focus_id
                                ), 0)
                            , 2)
                    )
                    ELSE NULL
                END',
            ],
            self::PREFIX_STATS.'.conversion_rate_click' => [
                'label'   => 'mautic.report.focus.ratio.click',
                'type'    => 'html',
                'alias'   => 'conversion_rate_click',
                'formula' => 'CASE
                    WHEN '.self::PREFIX_STATS.'.type = "click" THEN (
                        SELECT
                            ROUND(
                                (
                                    SELECT COUNT(fs2.id)
                                    FROM '.MAUTIC_TABLE_PREFIX.'focus_stats fs2
                                    WHERE fs2.type = "click"
                                    AND fs2.focus_id = '.self::PREFIX_STATS.'.focus_id
                                ) * 100.0 /
                                NULLIF((
                                    SELECT COUNT(fs3.id)
                                    FROM '.MAUTIC_TABLE_PREFIX.'focus_stats fs3
                                    WHERE fs3.type = "view"
                                    AND fs3.focus_id = '.self::PREFIX_STATS.'.focus_id
                                ), 0)
                            , 2)
                    )
                    ELSE NULL
                END',
            ],
            self::PREFIX_REDIRECTS.'.url' => [
                'label'   => 'url',
                'type'    => 'html',
                'alias'   => 'redirect_url',
                'formula' => 'MAX('.self::PREFIX_REDIRECTS.'.url)',
            ],
        ];

        $data = [
            'display_name' => 'mautic.focus.graph.stats',
            'columns'      => $columns,
        ];

        $event->addTable(self::CONTEXT_FOCUS_STATS, $data, self::FOCUS_GROUP);
        $this->addFocusLeadsTable($event, $columns);
    }

    /**
     * @param array<string, array<string, string>> $columns
     */
    private function addFocusLeadsTable(ReportBuilderEvent $event, array $columns): void
    {
        $columnsLeads = [
            self::PREFIX_LEADS.'.id' => [
                'label'   => 'mautic.report.field.lead.id',
                'type'    => 'html',
                'alias'   => 'lead_id',
                'formula' => 'MAX('.self::PREFIX_LEADS.'.id)',
            ],
            self::PREFIX_LEADS.'.firstname' => [
                'label'   => 'mautic.report.field.lead.firstname',
                'type'    => 'html',
                'alias'   => 'lead_firstname',
                'formula' => 'MAX('.self::PREFIX_LEADS.'.firstname)',
            ],
            self::PREFIX_LEADS.'.lastname' => [
                'label'   => 'mautic.report.field.lead.lastname',
                'type'    => 'html',
                'alias'   => 'lead_lastname',
                'formula' => 'MAX('.self::PREFIX_LEADS.'.lastname)',
            ],
            self::PREFIX_LEADS.'.email' => [
                'label'   => 'mautic.report.field.lead.email',
                'type'    => 'html',
                'alias'   => 'lead_email',
                'formula' => 'MAX('.self::PREFIX_LEADS.'.email)',
            ],
            self::PREFIX_LEADS.'.company' => [
                'label'   => 'mautic.report.field.lead.company',
                'type'    => 'html',
                'alias'   => 'lead_firstname',
                'formula' => 'MAX('.self::PREFIX_LEADS.'.company)',
            ],
            self::PREFIX_TRACKABLES.'.hits' => [
                'label'   => 'mautic.report.focus.hits',
                'type'    => 'html',
                'alias'   => 'hit_count',
                'formula' => 'CASE 
                    WHEN '.self::PREFIX_STATS.'.type = "view" THEN (
                        SELECT COUNT(fs2.id) 
                        FROM '.MAUTIC_TABLE_PREFIX.'focus_stats fs2                          
                        WHERE fs2.type = "view"                         
                        AND fs2.focus_id = '.self::PREFIX_STATS.'.focus_id
                        AND fs2.lead_id = '.self::PREFIX_LEADS.'.id
                        GROUP BY fs2.focus_id
                    )
                    WHEN '.self::PREFIX_STATS.'.type = "submission" THEN (
                        SELECT COUNT(fs2.id) 
                        FROM '.MAUTIC_TABLE_PREFIX.'focus_stats fs2                         
                        WHERE fs2.type = "submission" 
                        AND fs2.focus_id = '.self::PREFIX_STATS.'.focus_id
                        AND fs2.lead_id = '.self::PREFIX_LEADS.'.id
                        GROUP BY fs2.focus_id
                    )
                    WHEN '.self::PREFIX_STATS.'.type = "click" THEN (
                        SELECT COUNT(fs2.id) 
                        FROM '.MAUTIC_TABLE_PREFIX.'focus_stats fs2                         
                        WHERE fs2.type = "click" 
                        AND fs2.focus_id = '.self::PREFIX_STATS.'.focus_id
                        AND fs2.lead_id = '.self::PREFIX_LEADS.'.id
                        GROUP BY fs2.focus_id
                    )
                END',
            ],
            self::PREFIX_STATS.'.conversion_rate_click' => [
                'label'   => 'mautic.report.focus.ratio.click',
                'type'    => 'html',
                'alias'   => 'conversion_rate_click',
                'formula' => 'CASE
                    WHEN '.self::PREFIX_STATS.'.type = "click" THEN (
                        SELECT
                            ROUND(
                                (
                                    SELECT COUNT(fs2.id)
                                    FROM '.MAUTIC_TABLE_PREFIX.'focus_stats fs2
                                    WHERE fs2.type = "click"
                                    AND fs2.focus_id = '.self::PREFIX_STATS.'.focus_id
                                    AND fs2.lead_id = '.self::PREFIX_LEADS.'.id
                                ) * 100.0 /
                                NULLIF((
                                    SELECT COUNT(fs3.id)
                                    FROM '.MAUTIC_TABLE_PREFIX.'focus_stats fs3
                                    WHERE fs3.type = "view"
                                    AND fs3.focus_id = '.self::PREFIX_STATS.'.focus_id
                                    AND fs3.lead_id = '.self::PREFIX_LEADS.'.id
                                ), 0)
                            , 2)
                    )
                    ELSE NULL
                END',
            ],
            self::PREFIX_STATS.'.conversion_rate_submission' => [
                'label'   => 'mautic.report.focus.ratio.submission',
                'type'    => 'html',
                'alias'   => 'conversion_rate_submission',
                'formula' => 'CASE
                    WHEN '.self::PREFIX_STATS.'.type = "submission" THEN (
                        SELECT
                            ROUND(
                                (
                                    SELECT COUNT(fs2.id)
                                    FROM '.MAUTIC_TABLE_PREFIX.'focus_stats fs2
                                    WHERE fs2.type = "submission"
                                    AND fs2.focus_id = '.self::PREFIX_STATS.'.focus_id
                                    AND fs2.lead_id = '.self::PREFIX_LEADS.'.id
                                ) * 100.0 /
                                NULLIF((
                                    SELECT COUNT(fs3.id)
                                    FROM '.MAUTIC_TABLE_PREFIX.'focus_stats fs3
                                    WHERE fs3.type = "view"
                                    AND fs3.focus_id = '.self::PREFIX_STATS.'.focus_id
                                    AND fs3.lead_id = '.self::PREFIX_LEADS.'.id
                                ), 0)
                            , 2)
                    )
                    ELSE NULL
                END',
            ],
        ];

        // all row lead records are unique
        unset($columns[self::PREFIX_TRACKABLES.'.unique_hits']);

        $data = [
            'display_name' => 'mautic.report.datasource.focus.leads',
            'columns'      => array_merge($columns, $columnsLeads),
        ];

        $event->addTable(self::CONTEXT_FOCUS_LEADS, $data, self::FOCUS_GROUP);
    }

    /**
     * Initialize the QueryBuilder object to generate reports from.
     */
    public function onReportGenerate(ReportGeneratorEvent $event): void
    {
        if (!$event->checkContext([self::CONTEXT_FOCUS_STATS, self::CONTEXT_FOCUS_LEADS])) {
            return;
        }

        $queryBuilder = $event->getQueryBuilder();
        $queryBuilder->from(MAUTIC_TABLE_PREFIX.'focus_stats', self::PREFIX_STATS)
            ->leftJoin(self::PREFIX_STATS, MAUTIC_TABLE_PREFIX.'focus', self::PREFIX_FOCUS,
                self::PREFIX_FOCUS.'.id = '.self::PREFIX_STATS.'.focus_id')
            ->leftJoin(self::PREFIX_STATS, MAUTIC_TABLE_PREFIX.'channel_url_trackables', self::PREFIX_TRACKABLES,
                self::PREFIX_TRACKABLES.'.channel_id = '.self::PREFIX_STATS.'.focus_id AND '.
                self::PREFIX_TRACKABLES.'.channel = "focus"')
            ->leftJoin(self::PREFIX_STATS, MAUTIC_TABLE_PREFIX.'page_redirects', self::PREFIX_REDIRECTS,
                self::PREFIX_REDIRECTS.'.id = '.self::PREFIX_TRACKABLES.'.redirect_id')
            ->orderBy(self::PREFIX_FOCUS.'.name', 'ASC')
            ->addOrderBy(self::PREFIX_STATS.'.type', 'ASC');

        if ($event->hasColumn(self::PREFIX_FOCUS.'.category')) {
            $queryBuilder->leftJoin(self::PREFIX_FOCUS, MAUTIC_TABLE_PREFIX.'categories', self::PREFIX_CATEGORIES,
                self::PREFIX_FOCUS.'.category_id = '.self::PREFIX_CATEGORIES.'.id');
        }

        switch ($event->getContext()) {
            case self::CONTEXT_FOCUS_LEADS:
                $queryBuilder->leftJoin(self::PREFIX_FOCUS, MAUTIC_TABLE_PREFIX.'leads', self::PREFIX_LEADS,
                    self::PREFIX_STATS.'.lead_id = '.self::PREFIX_LEADS.'.id');

                $queryBuilder->groupBy(
                    self::PREFIX_STATS.'.focus_id',
                    self::PREFIX_STATS.'.type',
                    self::PREFIX_STATS.'.lead_id'
                );

                break;
            case self::CONTEXT_FOCUS_STATS:
                $queryBuilder->groupBy(self::PREFIX_STATS.'.focus_id', self::PREFIX_STATS.'.type');
                break;
        }

        $event->applyDateFilters($queryBuilder, 'date_added', self::PREFIX_STATS);
        $event->setQueryBuilder($queryBuilder);
    }
}
