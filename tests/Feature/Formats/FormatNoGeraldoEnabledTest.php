<?php

namespace Tests\Feature\Formats;

use Tests\TestCase;

class FormatNoGeraldoEnabledTest extends TestCase
{
    // Format: is_no_geraldo_enabled flag
    // New boolean column on formats, editable via edit:config permission.

    // GET /formats

    public function test_is_no_geraldo_enabled_appears_in_format_list_response(): void
    {
        $this->markTestSkipped('is_no_geraldo_enabled appears in format list response');
    }

    public function test_formats_with_flag_true_return_true(): void
    {
        $this->markTestSkipped('Formats with the flag true return true');
    }

    public function test_formats_with_flag_false_return_false(): void
    {
        $this->markTestSkipped('Formats with the flag false return false');
    }

    // PATCH /formats/{id}

    public function test_user_with_edit_config_permission_can_set_is_no_geraldo_enabled_true(): void
    {
        $this->markTestSkipped('User with edit:config permission can set is_no_geraldo_enabled=true');
    }

    public function test_user_with_edit_config_permission_can_set_is_no_geraldo_enabled_false(): void
    {
        $this->markTestSkipped('User with edit:config permission can set is_no_geraldo_enabled=false');
    }

    public function test_no_edit_config_permission_returns_403_field_not_changed(): void
    {
        $this->markTestSkipped('No edit:config permission → 403, field not changed');
    }

    public function test_non_boolean_value_returns_422(): void
    {
        $this->markTestSkipped('Non-boolean value returns 422');
    }

    public function test_omitting_field_leaves_existing_value_unchanged(): void
    {
        $this->markTestSkipped('Omitting the field from the request leaves existing value unchanged');
    }
}
