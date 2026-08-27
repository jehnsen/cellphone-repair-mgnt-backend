<?php

use App\Models\Branch;
use App\Models\Product;
use App\Models\RepairTicket;
use App\Models\TicketPhoto;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('records a part swap', function () {
    $branch = Branch::factory()->create();
    [, $token] = userWithRole('technician', $branch);
    $ticket = RepairTicket::factory()->create(['branch_id' => $branch->id]);
    $product = Product::factory()->part()->create();

    $this->withToken($token)->postJson("/api/v1/tickets/{$ticket->ulid}/part-swaps", [
        'removed_description' => 'Cracked OEM screen assembly',
        'installed_product_ulid' => $product->ulid,
        'disposition' => 'retained_for_disposal',
    ])->assertStatus(201)
        ->assertJsonPath('data.removed_description', 'Cracked OEM screen assembly')
        ->assertJsonPath('data.disposition', 'retained_for_disposal')
        ->assertJsonPath('data.installed_product.ulid', $product->ulid);
});

it('attaches a signed url when a removed_photo_ulid is given', function () {
    Storage::fake('local');

    $branch = Branch::factory()->create();
    [, $token] = userWithRole('technician', $branch);
    $ticket = RepairTicket::factory()->create(['branch_id' => $branch->id]);
    $product = Product::factory()->part()->create();

    $photoResponse = $this->withToken($token)->postJson("/api/v1/tickets/{$ticket->ulid}/photos", [
        'phase' => 'pre_repair',
        'photo' => UploadedFile::fake()->image('removed-screen.jpg'),
    ])->assertStatus(201);

    $response = $this->withToken($token)->postJson("/api/v1/tickets/{$ticket->ulid}/part-swaps", [
        'removed_description' => 'Cracked OEM screen assembly',
        'removed_photo_ulid' => $photoResponse->json('data.ulid'),
        'installed_product_ulid' => $product->ulid,
        'disposition' => 'retained_for_disposal',
    ])->assertStatus(201);

    expect($response->json('data.removed_photo_url'))->toBeString()->not->toBeEmpty();
});

it('rejects a removed_photo_ulid that belongs to a different ticket', function () {
    $branch = Branch::factory()->create();
    [, $token] = userWithRole('technician', $branch);
    $ticket = RepairTicket::factory()->create(['branch_id' => $branch->id]);
    $otherTicket = RepairTicket::factory()->create(['branch_id' => $branch->id]);
    $photo = TicketPhoto::factory()->create(['repair_ticket_id' => $otherTicket->id]);
    $product = Product::factory()->part()->create();

    $this->withToken($token)->postJson("/api/v1/tickets/{$ticket->ulid}/part-swaps", [
        'removed_description' => 'Cracked OEM screen assembly',
        'removed_photo_ulid' => $photo->ulid,
        'installed_product_ulid' => $product->ulid,
        'disposition' => 'retained_for_disposal',
    ])->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_FAILED');
});
