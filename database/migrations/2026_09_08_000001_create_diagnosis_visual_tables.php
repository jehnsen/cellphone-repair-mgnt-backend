<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The counter-side diagnosis visualizer — pointing at the part rather than
 * naming it.
 *
 * Three tables, and deliberately not a fourth: there is no `ticket_diagnoses`
 * here because `repair_findings` already is one (one row per ticket, a
 * `defects` array from a controlled vocabulary, upserted through
 * PUT /tickets/{ticket}/finding). A second diagnosis record would be a second
 * answer to "what is wrong with this unit", which is exactly what that table's
 * unique constraint exists to prevent. The visualizer reads the finding's
 * defects — and, before a technician has been at the bench, the ticket's
 * intake `problem_tags` — and turns them into parts through `issue_part_map`.
 *
 * `device_parts` is generic across handsets on purpose. The shop sees whatever
 * walks in; hand-modelling a teardown per SKU is not a thing a two-branch
 * shop can keep up with. One parametric rig, primitive geometry, and a flat
 * slab silhouette reads as "a phone" well enough to point at a battery.
 */
return new class extends Migration
{
    public function up(): void
    {
        // The parts of the generic rig. Geometry lives here rather than in the
        // frontend so the scene is data, not a hardcoded model: adding a part
        // is a row, and the visualizer picks it up without a deploy.
        //
        // Units are millimetres against a nominal 150 x 72 x 8 mm handset,
        // origin at the centre of the slab, +x right, +y up the screen,
        // +z out of the display face. Any real phone is close enough to that
        // for "your charging port is at the bottom edge" to be true.
        Schema::create('device_parts', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();

            // Stable machine name — what issue_part_map and a stored snapshot
            // reference, so renaming the label never orphans a mapping.
            $table->string('key', 48)->unique();
            $table->string('label', 80);

            // App\Support\Diagnosis\PartCategory.
            $table->string('category', 16);

            // Plain language, for the customer reading over the technician's
            // shoulder — "the flat cable the charger plugs into", not a
            // part number.
            $table->text('blurb')->nullable();

            $table->decimal('pos_x', 8, 3)->default(0);
            $table->decimal('pos_y', 8, 3)->default(0);
            $table->decimal('pos_z', 8, 3)->default(0);

            $table->decimal('size_x', 8, 3);
            $table->decimal('size_y', 8, 3);
            $table->decimal('size_z', 8, 3);

            // Which way this part travels in the explode view, and how far at
            // full extension. A unit vector by convention, but not enforced —
            // a longer vector is a legitimate way to make one part lead.
            $table->decimal('explode_x', 6, 3)->default(0);
            $table->decimal('explode_y', 6, 3)->default(0);
            $table->decimal('explode_z', 6, 3)->default(1);
            $table->decimal('explode_distance', 8, 3)->default(0);

            // Draw/list order. Front-of-device first, so the list reads the
            // way the phone comes apart.
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
            $table->index('category');
        });

        // Which parts an issue implicates. Keyed by (source, key) against the
        // two vocabularies the shop already had rather than a new issue_types
        // table — see App\Support\Diagnosis\IssueSource for why the source is
        // part of the key ('screen' and 'battery' exist in both).
        //
        // Most issues name one part; "no power" honestly names three, and
        // saying so is better than picking one and being confidently wrong.
        Schema::create('issue_part_map', function (Blueprint $table): void {
            $table->id();

            $table->string('issue_source', 16);
            $table->string('issue_key', 48);
            $table->foreignId('device_part_id')->constrained()->cascadeOnDelete();

            // 1 is the part the technician means first. Ordering matters at
            // the counter: the camera view frames the leading part.
            $table->unsignedTinyInteger('rank')->default(1);

            $table->timestamps();

            $table->unique(['issue_source', 'issue_key', 'device_part_id'], 'issue_part_map_unique');
            $table->index(['issue_source', 'issue_key']);
        });

        // What the customer was actually shown, frozen.
        //
        // The live view is rebuilt from the finding every time it opens, so it
        // drifts the moment the finding is revised — fine for the bench, not
        // fine for a quote someone approved. A snapshot pins the image and the
        // exact part selection behind it. Storage mirrors ticket_photos
        // (disk/path/hash) rather than inventing a second convention.
        Schema::create('ticket_diagnosis_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();

            $table->foreignId('repair_ticket_id')->constrained()->cascadeOnDelete();

            $table->string('storage_disk', 20)->default('local');
            $table->string('storage_path');
            $table->char('sha256_hash', 64);

            // The selection this image was rendered from, by key, so the view
            // can be reproduced even if the image is lost.
            $table->string('issue_source', 16);
            $table->json('issue_keys');
            $table->json('part_keys');

            // Orbit position at capture, {x, y, z, target:{x, y, z}}. Nullable:
            // a snapshot taken at the default framing does not need one.
            $table->json('camera')->nullable();

            $table->text('note')->nullable();

            $table->foreignId('captured_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->index('repair_ticket_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_diagnosis_snapshots');
        Schema::dropIfExists('issue_part_map');
        Schema::dropIfExists('device_parts');
    }
};
