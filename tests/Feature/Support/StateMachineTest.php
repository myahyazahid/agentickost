<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\ModelStates\Exceptions\CouldNotPerformTransition;
use Tests\Fixtures\FixtureDocument;
use Tests\Fixtures\States\Archived;
use Tests\Fixtures\States\Draft;
use Tests\Fixtures\States\Published;

beforeEach(function () {
    // A temporary table does not end the test's wrapping transaction in MySQL.
    Schema::create('fixture_documents', function (Blueprint $table) {
        $table->temporary();
        $table->ulid('id')->primary();
        $table->string('status', 32);
        $table->timestamps();
    });
});

it('starts in the default state', function () {
    $document = FixtureDocument::query()->create();

    expect($document->status)->toBeInstanceOf(Draft::class);
});

it('allows a configured transition', function () {
    $document = FixtureDocument::query()->create();

    $document->status->transitionTo(Published::class);

    expect($document->fresh()?->status)->toBeInstanceOf(Published::class);
});

it('rejects a transition that is not configured', function () {
    $document = FixtureDocument::query()->create();
    $document->status->transitionTo(Archived::class);

    $document->status->transitionTo(Published::class);
})->throws(CouldNotPerformTransition::class);

it('rejects skipping transitionTo() by assigning the state directly', function () {
    $document = FixtureDocument::query()->create();
    $document->status->transitionTo(Archived::class);

    $document->status = new Published($document);
    $document->save();
})->throws(CouldNotPerformTransition::class);
