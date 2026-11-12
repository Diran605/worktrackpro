<?php

namespace Tests\Feature;

use App\Models\DocsAccessCode;
use App\Models\DocsAccessRequest;
use App\Models\DocsFeedback;
use App\Models\ManualChangeRequest;
use App\Models\ManualSection;
use App\Models\MessageRecipient;
use App\Models\Organisation;
use App\Models\System;
use App\Models\SystemCategory;
use App\Models\SystemMember;
use App\Models\User;
use App\Services\ManualReleaseService;
use App\Services\ManualService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The public documentation library (Phase 3B).
 */
class DocsLibraryTest extends TestCase
{
    use RefreshDatabase;

    private Organisation $org;
    private User $owner;
    private System $system;
    private ManualSection $intro;
    private ManualSection $admin;
    private ManualSection $adminChild;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->withoutVite();
        Storage::fake('local');

        $this->org = Organisation::factory()->create(['slug' => 'acme', 'name' => 'Acme']);
        $this->owner = $this->makeUser('worker', $this->org);

        $category = SystemCategory::create(['organisation_id' => $this->org->id, 'name' => 'Finance']);

        $this->system = System::create([
            'organisation_id' => $this->org->id,
            'name' => 'Payments Gateway',
            'summary' => 'Takes card payments',
            'category_id' => $category->id,
            'library_visibility' => System::LIBRARY_PUBLIC,
            'support_contact' => 'Call the payments desk',
            'escalation_notes' => 'Page the on-call engineer',
        ]);
        $this->system->members()->create(['user_id' => $this->owner->id, 'role' => SystemMember::ROLE_OWNER]);

        $this->intro = $this->publish('Getting started', '<h2>Before you begin</h2><p>Refund steps for payments.</p><h2>Next steps</h2><p>More.</p>');
        $this->admin = $this->publish('Administration', '<p>Secret admin settings.</p>');
        $this->adminChild = $this->publish('Rotating keys', '<p>Rotate the vault keys.</p>', $this->admin->id);
        $this->admin->update(['visibility' => ManualSection::VISIBILITY_RESTRICTED]);

        app(ManualReleaseService::class)->publish($this->system->fresh(), $this->owner, true, 'First public version');
    }

    private function publish(string $title, string $body, ?int $parentId = null): ManualSection
    {
        Sanctum::actingAs($this->owner);

        $id = $this->postJson("/api/v1/systems/{$this->system->slug}/manual/changes", array_filter([
            'type' => 'create',
            'title' => $title,
            'body' => $body,
            'proposed_parent_id' => $parentId,
        ]))->assertCreated()->json('data.id');
        $this->postJson("/api/v1/manual-changes/{$id}/submit")->assertOk();

        app('auth')->forgetGuards();

        return ManualSection::findOrFail(ManualChangeRequest::findOrFail($id)->manual_section_id);
    }

    private function makeCode(bool $includeRestricted = false, array $attributes = []): string
    {
        $code = new DocsAccessCode(array_merge(['organisation_id' => $this->org->id, 'name' => 'Client A', 'is_active' => true], $attributes));
        $plain = $code->assignNewCode();
        $code->save();
        $code->systems()->attach($this->system->id, ['include_restricted' => $includeRestricted]);

        return $plain;
    }

    // ---------------------------------------------------------------------------------------------
    // Addresses
    // ---------------------------------------------------------------------------------------------

    public function test_old_portal_addresses_redirect_to_staff(): void
    {
        $this->get('/login')->assertRedirect('/staff/login');
        $this->get('/knowledge/payments-gateway')->assertRedirect('/staff/knowledge/payments-gateway');
        $this->get('/staff/dashboard')->assertOk();
    }

    public function test_home_lists_public_systems_by_category_and_recent_versions(): void
    {
        System::create(['organisation_id' => $this->org->id, 'name' => 'Hidden Tool']);

        $this->get('/')
            ->assertOk()
            ->assertSee('Acme Documentation')
            ->assertSee('Finance')
            ->assertSee('Payments Gateway')
            ->assertSee('Version 1.0')
            ->assertDontSee('Hidden Tool');
    }

    public function test_other_organisations_are_served_under_org_slug_and_custom_domains(): void
    {
        $other = Organisation::factory()->create(['slug' => 'globex', 'name' => 'Globex', 'docs_domain' => 'docs.globex.test']);
        System::create(['organisation_id' => $other->id, 'name' => 'Globex CRM', 'library_visibility' => System::LIBRARY_PUBLIC]);

        $this->get('/org/globex')->assertOk()->assertSee('Globex CRM')->assertDontSee('Payments Gateway');
        $this->get('http://docs.globex.test/')->assertOk()->assertSee('Globex CRM');
        $this->get('/org/globex/docs/payments-gateway')->assertNotFound();
        $this->get('/org/nobody')->assertNotFound();
    }

    // ---------------------------------------------------------------------------------------------
    // Reading
    // ---------------------------------------------------------------------------------------------

    public function test_public_system_page_and_section_reader(): void
    {
        $this->get('/docs/payments-gateway')
            ->assertOk()
            ->assertSee('Getting started')
            ->assertSee('Call the payments desk')
            ->assertDontSee('Page the on-call engineer'); // escalation notes are for staff only

        $this->get("/docs/payments-gateway/{$this->intro->slug}")
            ->assertOk()
            ->assertSee('Refund steps for payments.')
            ->assertSee('id="before-you-begin"', false)
            ->assertSee('Updated in version 1.0');
    }

    public function test_hidden_systems_are_not_found(): void
    {
        $this->system->update(['library_visibility' => System::LIBRARY_HIDDEN]);

        $this->get('/docs/payments-gateway')->assertNotFound();
    }

    public function test_restricted_chapters_are_locked_for_visitors(): void
    {
        $this->get("/docs/payments-gateway/{$this->admin->slug}")
            ->assertForbidden()
            ->assertSee('This content is restricted')
            ->assertDontSee('Secret admin settings.');

        // A child of a restricted chapter is restricted too.
        $this->get("/docs/payments-gateway/{$this->adminChild->slug}")
            ->assertForbidden()
            ->assertDontSee('Rotate the vault keys.');
    }

    public function test_restricted_manual_needs_staff_or_a_code(): void
    {
        $this->system->update(['library_visibility' => System::LIBRARY_RESTRICTED]);

        $this->get('/docs/payments-gateway')->assertForbidden()->assertSee('I have an access code');
        $this->get("/docs/payments-gateway/{$this->intro->slug}")->assertForbidden();

        // Staff of the organisation can read everything.
        $this->actingAs($this->owner, 'web')
            ->get("/docs/payments-gateway/{$this->admin->slug}")
            ->assertOk()
            ->assertSee('Secret admin settings.');
    }

    public function test_staff_of_another_organisation_are_visitors(): void
    {
        $outsider = $this->makeUser('admin');

        $this->actingAs($outsider, 'web')
            ->get("/docs/payments-gateway/{$this->admin->slug}")
            ->assertForbidden();
    }

    // ---------------------------------------------------------------------------------------------
    // Access codes
    // ---------------------------------------------------------------------------------------------

    public function test_access_code_unlocks_restricted_chapters_and_returns_to_the_page(): void
    {
        $plain = $this->makeCode(includeRestricted: true);
        $return = "/docs/payments-gateway/{$this->admin->slug}";

        $this->post('/docs/access', ['code' => strtolower($plain), 'return' => $return])
            ->assertRedirect(url($return));

        $this->get($return)->assertOk()->assertSee('Secret admin settings.');
        $this->assertSame(1, DocsAccessCode::first()->use_count);

        $this->get('/docs/access')->assertOk()->assertSee('Active in this browser')->assertSee('Client A');
        $this->post('/docs/access/forget')->assertRedirect(url('/'));
        $this->get($return)->assertForbidden();
    }

    public function test_access_page_renders_without_codes(): void
    {
        $this->get('/docs/access')->assertOk()->assertSee('Unlock')->assertDontSee('Active in this browser');
    }

    public function test_code_without_restricted_chapters_opens_the_manual_only(): void
    {
        $this->system->update(['library_visibility' => System::LIBRARY_RESTRICTED]);
        $this->post('/docs/access', ['code' => $this->makeCode(includeRestricted: false)]);

        $this->get("/docs/payments-gateway/{$this->intro->slug}")->assertOk();
        $this->get("/docs/payments-gateway/{$this->admin->slug}")->assertForbidden();
    }

    public function test_invalid_expired_and_deactivated_codes_are_refused(): void
    {
        $this->from('/docs/access')->post('/docs/access', ['code' => 'NOPE-NOPE-NOPE'])
            ->assertRedirect('/docs/access')
            ->assertSessionHasErrors('code');

        $expired = $this->makeCode(true, ['expires_at' => now()->subDay()]);
        $this->post('/docs/access', ['code' => $expired])->assertSessionHasErrors('code');

        // A code that worked stops working as soon as it is deactivated.
        $plain = $this->makeCode(true, ['name' => 'Client B']);
        $this->post('/docs/access', ['code' => $plain])->assertSessionHasNoErrors();
        $this->get("/docs/payments-gateway/{$this->admin->slug}")->assertOk();

        DocsAccessCode::where('name', 'Client B')->update(['is_active' => false]);
        $this->get("/docs/payments-gateway/{$this->admin->slug}")->assertForbidden();
    }

    public function test_return_path_cannot_leave_the_site(): void
    {
        $this->post('/docs/access', ['code' => $this->makeCode(), 'return' => '//evil.test/x'])
            ->assertRedirect(url('/docs/access'));
    }

    public function test_codes_are_stored_as_a_hash_and_encrypted(): void
    {
        $plain = $this->makeCode();
        $row = \DB::table('docs_access_codes')->first();

        $this->assertNotSame($plain, $row->code);
        $this->assertSame(DocsAccessCode::hashCode($plain), $row->code_hash);
        $this->assertSame($plain, DocsAccessCode::first()->code);
    }

    public function test_request_access_notifies_the_owners(): void
    {
        $this->post('/docs/payments-gateway/request-access', ['name' => 'Jane Client', 'email' => 'jane@example.test', 'reason' => 'Onboarding'])
            ->assertSessionHas('docs_request_sent');

        $this->assertSame(1, DocsAccessRequest::count());
        $this->assertTrue(MessageRecipient::query()->where('recipient_id', $this->owner->id)->exists());
    }

    // ---------------------------------------------------------------------------------------------
    // Versions, search, feedback, sitemap
    // ---------------------------------------------------------------------------------------------

    public function test_changes_page_lists_versions_and_hides_restricted_section_names(): void
    {
        $this->get('/docs/payments-gateway/changes')
            ->assertOk()
            ->assertSee('Version 1.0')
            ->assertSee('First public version')
            ->assertSee('Getting started')
            ->assertDontSee('Rotating keys')
            ->assertSee('2 restricted sections also changed.');
    }

    public function test_publishing_a_version_needs_pending_changes_and_an_owner(): void
    {
        $service = app(ManualReleaseService::class);

        $this->expectException(ValidationException::class);
        $service->publish($this->system->fresh(), $this->owner, false, 'Nothing new');
    }

    public function test_approved_changes_wait_for_the_next_version(): void
    {
        Sanctum::actingAs($this->makeUser('worker', $this->org));
        $id = $this->postJson("/api/v1/systems/{$this->system->slug}/manual/changes", ['type' => 'create', 'title' => 'Disputes', 'body' => '<p>x</p>'])->json('data.id');
        $this->postJson("/api/v1/manual-changes/{$id}/submit")->assertOk();
        app(ManualService::class)->approve(ManualChangeRequest::find($id), $this->owner);

        $this->assertSame('1.0', $this->system->fresh()->manual_version);
        $this->assertCount(1, app(ManualReleaseService::class)->upcomingChanges($this->system->fresh()));

        $release = app(ManualReleaseService::class)->publish($this->system->fresh(), $this->owner, false, 'Added disputes');
        $this->assertSame('1.1', $release->version);
    }

    public function test_library_shows_the_last_published_version_until_the_owner_publishes(): void
    {
        // The owner edits a section and adds a new one: approved straight away (staff see it)…
        Sanctum::actingAs($this->owner);
        $id = $this->postJson("/api/v1/systems/{$this->system->slug}/manual/changes", [
            'type' => 'edit', 'manual_section_id' => $this->intro->id,
        ])->json('data.id');
        $this->patchJson("/api/v1/manual-changes/{$id}", ['title' => 'Getting started fast', 'body' => '<p>Brand new wording.</p>'])->assertOk();
        $this->postJson("/api/v1/manual-changes/{$id}/submit")->assertOk();
        $disputes = $this->publish('Disputes', '<p>Chargeback disputes.</p>');
        app('auth')->forgetGuards();

        Sanctum::actingAs($this->owner);
        $this->getJson("/api/v1/systems/{$this->system->slug}/manual/sections/{$this->intro->id}")
            ->assertJsonPath('data.title', 'Getting started fast');
        app('auth')->forgetGuards();

        // …but library readers still get version 1.0.
        $this->get("/docs/payments-gateway/{$this->intro->slug}")
            ->assertOk()
            ->assertSee('Refund steps for payments.')
            ->assertDontSee('Brand new wording.')
            ->assertSee('Getting started')
            ->assertDontSee('Getting started fast');
        $this->get("/docs/payments-gateway/{$disputes->slug}")->assertNotFound();
        $this->get('/docs/search?q=chargeback')->assertSee('No results');
        $this->get('/docs/payments-gateway')->assertDontSee('Disputes');

        // Publishing a version releases both.
        app(ManualReleaseService::class)->publish($this->system->fresh(), $this->owner, false, 'Clearer intro, disputes');

        $this->get("/docs/payments-gateway/{$this->intro->slug}")
            ->assertSee('Brand new wording.')
            ->assertSee('Getting started fast')
            ->assertSee('Updated in version 1.1');
        $this->get("/docs/payments-gateway/{$disputes->slug}")->assertOk()->assertSee('Chargeback disputes.');
    }

    public function test_library_numbers_have_no_gaps_for_unreleased_sections(): void
    {
        // A new chapter placed first is not released yet: the library still numbers from 1.
        $new = $this->publish('Welcome', '<p>Hello</p>');
        app(ManualService::class)->moveTo($new, null);
        $outline = app(ManualService::class)->libraryOutline($this->system->fresh());

        $this->assertFalse($outline->contains('id', $new->id));
        $this->assertSame(['1', '2', '2.1'], $outline->pluck('number')->all());
    }

    public function test_search_finds_readable_sections_only(): void
    {
        $this->get('/docs/search?q=refund')->assertOk()->assertSee('Getting started');
        $this->get('/docs/search?q=vault')->assertOk()->assertSee('No results');

        $this->actingAs($this->owner, 'web')
            ->get('/docs/search?q=vault')->assertOk()->assertSee('Rotating keys');
    }

    public function test_feedback_is_one_vote_per_visitor(): void
    {
        $url = "/docs/payments-gateway/{$this->intro->slug}/feedback";

        $this->post($url, ['helpful' => '0', 'comment' => 'Missing a step'])->assertRedirect();
        $this->post($url, ['helpful' => '1'])->assertRedirect();

        $this->assertSame(1, DocsFeedback::count());
        $this->assertTrue(DocsFeedback::first()->helpful);

        $this->post("/docs/payments-gateway/{$this->admin->slug}/feedback", ['helpful' => '1'])->assertForbidden();
    }

    public function test_sitemap_lists_public_content_only(): void
    {
        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertSee("/docs/payments-gateway/{$this->intro->slug}", false)
            ->assertDontSee("/docs/payments-gateway/{$this->admin->slug}", false);
    }

    public function test_pdf_leaves_out_locked_sections(): void
    {
        $response = $this->get('/docs/payments-gateway/pdf');
        $this->assertTrue($response->isRedirect() || $response->getStatusCode() === 200);

        $export = \App\Models\ManualExport::latest('id')->first();
        $this->assertNotNull($export);
        $this->assertEqualsCanonicalizing([$this->admin->id, $this->adminChild->id], $export->options['exclude'] ?? []);
    }

    public function test_portal_manual_shows_library_details(): void
    {
        Sanctum::actingAs($this->owner);

        $this->getJson("/api/v1/systems/{$this->system->slug}/manual")
            ->assertOk()
            ->assertJsonPath('data.library.visibility', System::LIBRARY_PUBLIC)
            ->assertJsonPath('data.library.path', '/docs/payments-gateway')
            ->assertJsonPath('data.unreleased_changes', 0)
            ->assertJsonPath('data.tree.1.restricted', true);

        $this->getJson("/api/v1/systems/{$this->system->slug}/manual/sections/{$this->intro->id}")
            ->assertJsonPath('data.library_path', "/docs/payments-gateway/{$this->intro->slug}");

        // Another organisation's library is under /org/{slug}.
        $other = $this->makeUser('worker');
        $theirs = System::create(['organisation_id' => $other->organisation_id, 'name' => 'Their CRM', 'library_visibility' => System::LIBRARY_PUBLIC]);
        Sanctum::actingAs($other);
        $this->getJson("/api/v1/systems/{$theirs->slug}/manual")
            ->assertJsonPath('data.library.path', "/org/{$other->organisation->slug}/docs/their-crm")
            ->assertJsonPath('data.unreleased_changes', null);
    }

    // ---------------------------------------------------------------------------------------------
    // Staff handoff
    // ---------------------------------------------------------------------------------------------

    public function test_staff_sign_in_goes_through_the_portal_and_back_to_the_page(): void
    {
        $page = "/docs/payments-gateway/{$this->intro->slug}";

        $this->get($page)->assertOk()
            ->assertSee(url('/staff/library') . '?return=' . urlencode($page), false);
        $this->get('/staff/library?return=' . urlencode($page))->assertOk(); // the portal page
    }

    public function test_staff_portal_can_open_a_page_in_the_library_signed_in(): void
    {
        Sanctum::actingAs($this->owner);
        $url = $this->postJson('/api/v1/auth/docs-link', ['path' => "/docs/payments-gateway/{$this->admin->slug}"])
            ->assertOk()->json('url');

        $this->get($url)->assertRedirect(url("/docs/payments-gateway/{$this->admin->slug}"));
        $this->assertAuthenticatedAs($this->owner, 'web');

        $this->postJson('/api/v1/auth/docs-link', ['path' => '//evil.test'])->assertStatus(422);
    }
}
