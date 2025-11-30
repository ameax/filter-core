<?php

namespace Ameax\FilterCore\Tests\Selections;

use Ameax\FilterCore\Selections\FilterSelection;
use Ameax\FilterCore\Tests\Filters\KoiStatusFilter;
use Ameax\FilterCore\Tests\Filters\KoiCountFilter;
use Ameax\FilterCore\Tests\Filters\KoiNameFilter;
use Ameax\FilterCore\Tests\Models\Koi;
use Ameax\FilterCore\Tests\TestCase;

class FilterSelectionDebugTest extends TestCase
{
    // =========================================================================
    // toSql()
    // =========================================================================

    public function test_to_sql_with_model_class(): void
    {
        $selection = FilterSelection::make()
            ->forModel(Koi::class)
            ->where(KoiStatusFilter::class)->is('active');

        $sql = $selection->toSql();

        $this->assertStringContainsString('select', strtolower($sql));
        $this->assertStringContainsString('kois', strtolower($sql));
        $this->assertStringContainsString('status', strtolower($sql));
        $this->assertStringContainsString('?', $sql);
    }

    public function test_to_sql_with_provided_query(): void
    {
        $selection = FilterSelection::make()
            ->where(KoiStatusFilter::class)->is('active');

        $sql = $selection->toSql(Koi::query());

        $this->assertStringContainsString('select', strtolower($sql));
        $this->assertStringContainsString('?', $sql);
    }

    public function test_to_sql_throws_without_model_or_query(): void
    {
        $selection = FilterSelection::make()
            ->where(KoiStatusFilter::class)->is('active');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Cannot generate SQL without a model class or query');

        $selection->toSql();
    }

    // =========================================================================
    // toSqlWithBindings()
    // =========================================================================

    public function test_to_sql_with_bindings_string_value(): void
    {
        $selection = FilterSelection::make()
            ->forModel(Koi::class)
            ->where(KoiStatusFilter::class)->is('active');

        $sql = $selection->toSqlWithBindings();

        $this->assertStringContainsString("'active'", $sql);
        $this->assertStringNotContainsString('?', $sql);
    }

    public function test_to_sql_with_bindings_numeric_value(): void
    {
        $selection = FilterSelection::make()
            ->forModel(Koi::class)
            ->where(KoiCountFilter::class)->gt(10);

        $sql = $selection->toSqlWithBindings();

        $this->assertStringContainsString('10', $sql);
        $this->assertStringNotContainsString('?', $sql);
    }

    public function test_to_sql_with_bindings_multiple_values(): void
    {
        $selection = FilterSelection::make()
            ->forModel(Koi::class)
            ->where(KoiStatusFilter::class)->is('active')
            ->where(KoiCountFilter::class)->gt(5);

        $sql = $selection->toSqlWithBindings();

        $this->assertStringContainsString("'active'", $sql);
        $this->assertStringContainsString('5', $sql);
        $this->assertStringNotContainsString('?', $sql);
    }

    // =========================================================================
    // explain()
    // =========================================================================

    public function test_explain_single_filter(): void
    {
        $selection = FilterSelection::make()
            ->where(KoiStatusFilter::class)->is('active');

        $explanation = $selection->explain();

        $this->assertStringContainsString('KoiStatusFilter', $explanation);
        $this->assertStringContainsString('IS', $explanation);
        $this->assertStringContainsString("'active'", $explanation);
    }

    public function test_explain_multiple_filters(): void
    {
        $selection = FilterSelection::make()
            ->where(KoiStatusFilter::class)->is('active')
            ->where(KoiCountFilter::class)->gt(10);

        $explanation = $selection->explain();

        $this->assertStringContainsString('KoiStatusFilter', $explanation);
        $this->assertStringContainsString('KoiCountFilter', $explanation);
        $this->assertStringContainsString('AND', $explanation);
        $this->assertStringContainsString('IS', $explanation);
        $this->assertStringContainsString('GT', $explanation);
    }

    public function test_explain_or_selection(): void
    {
        $selection = FilterSelection::makeOr()
            ->where(KoiStatusFilter::class)->is('active')
            ->where(KoiStatusFilter::class)->is('pending');

        $explanation = $selection->explain();

        $this->assertStringContainsString('OR', $explanation);
    }

    public function test_explain_nested_groups(): void
    {
        $selection = FilterSelection::make()
            ->where(KoiStatusFilter::class)->is('active')
            ->orWhere(function ($group) {
                $group->where(KoiStatusFilter::class)->is('pending');
                $group->where(KoiCountFilter::class)->gt(5);
            });

        $explanation = $selection->explain();

        $this->assertStringContainsString('AND', $explanation);
        $this->assertStringContainsString('(', $explanation);
        $this->assertStringContainsString(')', $explanation);
    }

    public function test_explain_array_value(): void
    {
        $selection = FilterSelection::make()
            ->where(KoiStatusFilter::class)->any(['active', 'pending']);

        $explanation = $selection->explain();

        $this->assertStringContainsString("['active', 'pending']", $explanation);
    }

    public function test_explain_null_value(): void
    {
        $selection = FilterSelection::make()
            ->where(KoiStatusFilter::class)->is(null);

        $explanation = $selection->explain();

        $this->assertStringContainsString('NULL', $explanation);
    }

    public function test_explain_boolean_value(): void
    {
        $selection = FilterSelection::make()
            ->where(KoiStatusFilter::class)->is(true);

        $explanation = $selection->explain();

        $this->assertStringContainsString('true', $explanation);
    }

    // =========================================================================
    // debug()
    // =========================================================================

    public function test_debug_returns_all_info(): void
    {
        $selection = FilterSelection::make()
            ->forModel(Koi::class)
            ->where(KoiStatusFilter::class)->is('active');

        $debug = $selection->debug();

        $this->assertArrayHasKey('sql', $debug);
        $this->assertArrayHasKey('sql_with_bindings', $debug);
        $this->assertArrayHasKey('bindings', $debug);
        $this->assertArrayHasKey('filters', $debug);
        $this->assertArrayHasKey('explanation', $debug);

        $this->assertStringContainsString('select', strtolower($debug['sql']));
        $this->assertStringContainsString("'active'", $debug['sql_with_bindings']);
        $this->assertContains('active', $debug['bindings']);
        $this->assertContains('KoiStatusFilter', $debug['filters']);
        $this->assertStringContainsString('KoiStatusFilter', $debug['explanation']);
    }

    // =========================================================================
    // EDGE CASES
    // =========================================================================

    public function test_to_sql_empty_selection(): void
    {
        $selection = FilterSelection::make()
            ->forModel(Koi::class);

        $sql = $selection->toSql();

        $this->assertStringContainsString('select', strtolower($sql));
        // No WHERE clause for empty selection
    }

    public function test_explain_empty_selection(): void
    {
        $selection = FilterSelection::make();

        $explanation = $selection->explain();

        $this->assertEquals('', $explanation);
    }

    public function test_to_sql_with_bindings_escapes_quotes(): void
    {
        $selection = FilterSelection::make()
            ->forModel(Koi::class)
            ->where(KoiNameFilter::class)->contains("it's test");

        $sql = $selection->toSqlWithBindings();

        // Single quotes should be escaped
        $this->assertStringContainsString("it''s test", $sql);
    }
}
