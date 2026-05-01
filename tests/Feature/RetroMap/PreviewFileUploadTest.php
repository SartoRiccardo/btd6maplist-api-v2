<?php

namespace Tests\Feature\RetroMap;

use Tests\TestCase;

class PreviewFileUploadTest extends TestCase
{
    // POST/PUT /retro-maps — preview_file Upload
    // Either preview_url or preview_file must be provided. File stored to public/retro_map_previews/.

    // POST (create)

    public function test_creating_with_preview_url_only_works_as_before(): void
    {
        $this->markTestSkipped('Creating with preview_url only works as before');
    }

    public function test_creating_with_preview_file_valid_jpg_stores_file_and_returns_public_url(): void
    {
        $this->markTestSkipped('Creating with preview_file (valid jpg) stores file and returns a public URL');
    }

    public function test_stored_filename_is_a_uuid_with_the_files_extension(): void
    {
        $this->markTestSkipped('Stored filename is a UUID with the file\'s extension');
    }

    public function test_returned_preview_url_points_to_the_stored_file(): void
    {
        $this->markTestSkipped('Returned preview_url points to the stored file');
    }

    public function test_create_neither_preview_url_nor_preview_file_provided_returns_422(): void
    {
        $this->markTestSkipped('Neither preview_url nor preview_file provided → 422');
    }

    public function test_create_file_over_4_5_mb_returns_422(): void
    {
        $this->markTestSkipped('File over 4.5 MB → 422');
    }

    public function test_create_unsupported_mime_type_returns_422(): void
    {
        $this->markTestSkipped('Unsupported MIME type (e.g. pdf) → 422');
    }

    public function test_create_both_preview_url_and_preview_file_provided_file_takes_precedence(): void
    {
        $this->markTestSkipped('Both preview_url and preview_file provided — file takes precedence (or whichever the implementation chose; verify it\'s consistent)');
    }

    // PUT (update)

    public function test_updating_with_preview_file_replaces_old_preview_stored_as_id_ext(): void
    {
        $this->markTestSkipped('Updating with preview_file replaces old preview, stored as `{id}.ext`');
    }

    public function test_updating_with_preview_url_only_does_not_create_a_file(): void
    {
        $this->markTestSkipped('Updating with preview_url only does not create a file');
    }

    public function test_update_file_over_4_5_mb_returns_422(): void
    {
        $this->markTestSkipped('File over 4.5 MB → 422');
    }

    public function test_update_unsupported_mime_type_returns_422(): void
    {
        $this->markTestSkipped('Unsupported MIME type → 422');
    }

    public function test_update_with_neither_preview_url_nor_preview_file_returns_422(): void
    {
        $this->markTestSkipped('Update with neither preview_url nor preview_file → 422');
    }
}
