
<?php

use App\Enums\MunicipalityVariableType;
use App\Enums\Role;
use App\Filament\Admin\Resources\MunicipalityVariables\Pages\CreateMunicipalityVariable;
use App\Filament\Municipality\Clusters\Settings\Resources\MunicipalityVariables\Pages\CreateMunicipalityVariable as PagesCreateMunicipalityVariable;
use App\Models\Municipality;
use App\Models\MunicipalityVariable;
use App\Models\User;
use App\Models\Users\MunicipalityAdminUser;
use Filament\Facades\Filament;
use Laravel\Passport\Client;

use function Pest\Livewire\livewire;

test('allows admins to manage default variables across all municipalities', function () {
    // arrange: admin user, two municipalities, a default variable definition
    $admin = User::factory()->create([
        'email' => 'advisor@example.com',
        'role' => Role::Admin,
    ]);
    $municipality1 = Municipality::factory()->create();
    $municipality2 = Municipality::factory()->create();

    // act: admin creates a default variable
    $defaultVariable = MunicipalityVariable::factory()->create([
        'municipality_id' => null,
        'name' => 'School Year',
        'key' => 'school_year',
        'type' => MunicipalityVariableType::Text,
        'value' => '2024-2025',
        'is_default' => true,
    ]);

    // act: admin updates the default variable
    $defaultVariable->update([
        'value' => '2025-2026',
        'name' => 'Academic Year',
    ]);

    // assert: changes are authorized for admin
    expect($defaultVariable->fresh())
//        ->value->toBe('2025-2026')
        ->name->toBe('Academic Year')
        ->is_default->toBe(true)
        ->municipality_id->toBeNull();

    // act: admin deletes the default variable
    $defaultVariable->delete();

    // assert: variable is soft deleted
    expect($defaultVariable->fresh()->trashed())->toBe(true);
});

test('seeds default variables when a municipality is created', function () {
    // arrange: have N default definitions
    $defaultVariables = collect([
        MunicipalityVariable::factory()->create([
            'municipality_id' => null,
            'name' => 'School Year',
            'key' => 'school_year',
            'type' => MunicipalityVariableType::Text,
            'value' => '2024-2025',
            'is_default' => true,
        ]),
        MunicipalityVariable::factory()->create([
            'municipality_id' => null,
            'name' => 'Budget Limit',
            'key' => 'budget_limit',
            'type' => MunicipalityVariableType::Number,
            'value' => 10000,
            'is_default' => true,
        ]),
        MunicipalityVariable::factory()->create([
            'municipality_id' => null,
            'name' => 'Registration Open',
            'key' => 'registration_open',
            'type' => MunicipalityVariableType::Boolean,
            'value' => true,
            'is_default' => true,
        ]),
    ]);

    // act: create municipality
    $municipality = Municipality::factory()->create();

    // assert: municipality_variables created matching defaults
    expect($municipality->variables()->count())->toBe(3);

    $municipality->variables()->get()->each(function ($variable) use ($defaultVariables) {
        $matchingDefault = $defaultVariables->firstWhere('key', $variable->key);
        expect($variable)
            ->name->toBe($matchingDefault->name)
            ->key->toBe($matchingDefault->key)
            ->type->toBe($matchingDefault->type)
            ->value->toBe($matchingDefault->value)
            ->is_default->toBe(true)
            ->municipality_id->toBe($variable->municipality_id);
    });
});

test('syncs newly added default variables to all existing municipalities', function () {
    // arrange: existing municipalities without the new default
    $municipality1 = Municipality::factory()->create();
    $municipality2 = Municipality::factory()->create();

    // Ensure municipalities start with no variables
    expect($municipality1->variables()->count())->toBe(0);
    expect($municipality2->variables()->count())->toBe(0);

    // act: add a new default variable definition
    $newDefaultVariable = MunicipalityVariable::factory()->create([
        'municipality_id' => null,
        'name' => 'New Default Setting',
        'key' => 'new_default',
        'type' => MunicipalityVariableType::Text,
        'value' => 'default value',
        'is_default' => true,
    ]);

    expect($municipality1->variables()->count())->toBe(1);
    expect($municipality2->variables()->count())->toBe(1);

    expect($municipality1->variables()->first())
        ->name->toBe('New Default Setting')
        ->key->toBe('new_default')
        ->is_default->toBe(true);

    expect($municipality2->variables()->first())
        ->name->toBe('New Default Setting')
        ->key->toBe('new_default')
        ->is_default->toBe(true);
});

test('returns municipality variables via the API endpoint', function () {
    // arrange: create variables
    $municipality = Municipality::factory()->create();

    $variable1 = MunicipalityVariable::factory()->create([
        'municipality_id' => $municipality->id,
        'name' => 'Text Variable',
        'key' => 'text_var',
        'type' => MunicipalityVariableType::Text,
        'value' => 'Hello World',
        'is_default' => true,
    ]);

    $variable2 = MunicipalityVariable::factory()->create([
        'municipality_id' => $municipality->id,
        'name' => 'Number Variable',
        'key' => 'number_var',
        'type' => MunicipalityVariableType::Number,
        'value' => 42,
        'is_default' => false,
    ]);

    // act
    $client = Client::factory()->asClientCredentials()->create(['secret' => '12345678']);

    $response = $this->postJson(route('passport.token'), [
        'grant_type' => 'client_credentials',
        'client_id' => $client->id,
        'client_secret' => '12345678',
    ]);

    $body = $response->json();
    $this->access_token = $body['access_token'];

    $response = $this->getJson(route('api.municipality-variables', $municipality->brk_identification), [
        'Authorization' => 'Bearer '.$this->access_token,
    ]);

    // assert: 200 + JSON shape + values
    $response->assertStatus(200);
    $response->assertJsonStructure([
        'data' => [
            '*' => [
                'id',
                'name',
                'key',
                'type',
                'value',
                'is_default',
            ],
        ],
    ]);

    $responseData = $response->json('data');
    expect($responseData)->toHaveCount(2);

    $textVar = collect($responseData)->firstWhere('key', 'text_var');
    $numberVar = collect($responseData)->firstWhere('key', 'number_var');

    expect($textVar)
        ->name->toBe('Text Variable')
        ->value->toBe('Hello World')
        ->is_default->toBe(true);

    expect($numberVar)
        ->name->toBe('Number Variable')
        ->value->toBe(42)
        ->is_default->toBe(false);
});

test('stores the origin of a variable as is_default', function () {
    // arrange: one default-seeded, one custom-created variable
    $municipality = Municipality::factory()->create();

    $defaultVariable = MunicipalityVariable::factory()->create([
        'municipality_id' => $municipality->id,
        'name' => 'Default Variable',
        'key' => 'default_var',
        'type' => MunicipalityVariableType::Text,
        'value' => 'default value',
        'is_default' => true,
    ]);

    $customVariable = MunicipalityVariable::factory()->create([
        'municipality_id' => $municipality->id,
        'name' => 'Custom Variable',
        'key' => 'custom_var',
        'type' => MunicipalityVariableType::Text,
        'value' => 'custom value',
        'is_default' => false,
    ]);

    // assert: origin flag/enum correct for each
    expect($defaultVariable->is_default)->toBe(true);
    expect($customVariable->is_default)->toBe(false);

    $variables = $municipality->variables()->get();
    $defaultFromDb = $variables->firstWhere('key', 'default_var');
    $customFromDb = $variables->firstWhere('key', 'custom_var');

    expect($defaultFromDb->is_default)->toBe(true);
    expect($customFromDb->is_default)->toBe(false);
});

test('restricts editing to admins of the given municipality', function () {
    // arrange: user not admin of this municipality
    $municipality1 = Municipality::factory()->create();
    $municipality2 = Municipality::factory()->create();

    $municipalityAdmin = User::factory()->create([
        'email' => 'advisor@example.com',
        'role' => Role::ReviewerMunicipalityAdmin,
    ]);
    $municipalityAdmin->municipalities()->attach($municipality1);

    $variable = MunicipalityVariable::factory()->create([
        'municipality_id' => $municipality2->id,
        'name' => 'Test Variable',
        'key' => 'test_var',
        'type' => MunicipalityVariableType::Text,
        'value' => 'test value',
        'is_default' => false,
    ]);

    // act & assert: try to update variable - policy should deny
    expect($municipalityAdmin->can('update', $variable))->toBe(false);
    expect($municipalityAdmin->can('view', $variable))->toBe(false);
    expect($municipalityAdmin->can('delete', $variable))->toBe(false);
});

test('allows municipality admins to fully edit custom variables', function () {
    // arrange: custom variable
    $municipality = Municipality::factory()->create();
    $municipalityAdmin = User::factory()->create([
        'email' => 'advisor@example.com',
        'role' => Role::ReviewerMunicipalityAdmin,
    ]);
    $municipalityAdmin->municipalities()->attach($municipality);

    $customVariable = MunicipalityVariable::factory()->create([
        'municipality_id' => $municipality->id,
        'name' => 'Original Name',
        'key' => 'original_key',
        'type' => MunicipalityVariableType::Text,
        'value' => 'original value',
        'is_default' => false,
    ]);

    // act: update label/value/type/key
    expect($municipalityAdmin->can('update', $customVariable))->toBe(true);

    $customVariable->update([
        'name' => 'Updated Name',
        'key' => 'updated_key',
        'type' => MunicipalityVariableType::Number,
        'value' => 123,
    ]);

    // assert: persisted
    expect($customVariable->fresh())
        ->name->toBe('Updated Name')
        ->key->toBe('updated_key')
        ->type->toBe(MunicipalityVariableType::Number)
        ->value->toBe(123)
        ->is_default->toBe(false);
});

// test('prevents municipality admins from changing key and type of default variables but allows other fields', function () {
//    // arrange: default variable
//    $municipality = Municipality::factory()->create();
//    $municipalityAdmin = MunicipalityAdminUser::factory()->create();
//    $municipalityAdmin->municipalities()->attach($municipality);
//
//    $defaultVariable = MunicipalityVariable::factory()->create([
//        'municipality_id' => $municipality->id,
//        'name' => 'Original Name',
//        'key' => 'original_key',
//        'type' => MunicipalityVariableType::Text,
//        'value' => 'original value',
//        'is_default' => true,
//    ]);
//
//    // act: attempt to change key/type and name/value
//    expect($municipalityAdmin->can('update', $defaultVariable))->toBe(true);
//
//    // This would typically be handled by form validation or model mutators
//    // For now, we'll simulate the business logic
//    $originalKey = $defaultVariable->key;
//    $originalType = $defaultVariable->type;
//
//    $defaultVariable->update([
//        'name' => 'Updated Name', // Should be allowed
//        'value' => 'updated value', // Should be allowed
//        'key' => 'attempted_new_key', // Should be prevented
//        'type' => MunicipalityVariableType::Number, // Should be prevented
//    ]);
//
//    // assert: key/type unchanged; other fields updated
//    // Note: In a real implementation, you'd have validation rules or mutators
//    // preventing key/type changes for default variables
//    expect($defaultVariable->fresh())
//        ->name->toBe('Updated Name')
//        ->value->toBe('updated value')
//        ->key->toBe($originalKey) // Should remain unchanged in real implementation
//        ->type->toBe($originalType); // Should remain unchanged in real implementation
// });

test('enforces unique keys per municipality', function () {
    // arrange: create variable with key 'schoolyear'
    $municipality = Municipality::factory()->create();

    MunicipalityVariable::factory()->create([
        'municipality_id' => $municipality->id,
        'key' => 'schoolyear',
        'name' => 'School Year',
        'type' => MunicipalityVariableType::Text,
        'value' => '2024-2025',
        'is_default' => false,
    ]);

    // act & assert: attempt duplicate key in same municipality
    expect(function () use ($municipality) {
        MunicipalityVariable::factory()->create([
            'municipality_id' => $municipality->id,
            'key' => 'schoolyear', // Duplicate key
            'name' => 'Another School Year',
            'type' => MunicipalityVariableType::Text,
            'value' => '2025-2026',
            'is_default' => false,
        ]);
    })->toThrow(\Illuminate\Database\QueryException::class);

    // But different municipalities should allow same key
    $anotherMunicipality = Municipality::factory()->create();

    $variableInAnotherMunicipality = MunicipalityVariable::factory()->create([
        'municipality_id' => $anotherMunicipality->id,
        'key' => 'schoolyear', // Same key, different municipality
        'name' => 'School Year Other Municipality',
        'type' => MunicipalityVariableType::Text,
        'value' => '2024-2025',
        'is_default' => false,
    ]);

    expect($variableInAnotherMunicipality)->toBeInstanceOf(MunicipalityVariable::class);
});

test('validates type-specific values (text, number, bool, date_range)', function () {
    $municipality = Municipality::factory()->create();

    // Text type - should accept strings
    $textVariable = MunicipalityVariable::factory()->create([
        'municipality_id' => $municipality->id,
        'key' => 'text_test',
        'type' => MunicipalityVariableType::Text,
        'value' => 'Valid text value',
        'is_default' => false,
    ]);
    expect($textVariable->formatted_value)->toBe('Valid text value');

    // Number type - should accept numeric values
    $numberVariable = MunicipalityVariable::factory()->create([
        'municipality_id' => $municipality->id,
        'key' => 'number_test',
        'type' => MunicipalityVariableType::Number,
        'value' => 42.5,
        'is_default' => false,
    ]);
    expect($numberVariable->formatted_value)->toBe(42.5);

    // Boolean type - should accept boolean values
    $boolVariable = MunicipalityVariable::factory()->create([
        'municipality_id' => $municipality->id,
        'key' => 'bool_test',
        'type' => MunicipalityVariableType::Boolean,
        'value' => true,
        'is_default' => false,
    ]);
    expect($boolVariable->formatted_value)->toBe(true);

    // Date range type - should accept properly formatted date ranges
    $dateRangeVariable = MunicipalityVariable::factory()->create([
        'municipality_id' => $municipality->id,
        'key' => 'date_range_test',
        'type' => MunicipalityVariableType::DateRange,
        'value' => ['start' => '2024-01-01', 'end' => '2024-12-31'],
        'is_default' => false,
    ]);
    expect($dateRangeVariable->formatted_value)->toBe(['start' => '2024-01-01', 'end' => '2024-12-31']);

    // Time range type - should accept properly formatted time ranges
    $timeRangeVariable = MunicipalityVariable::factory()->create([
        'municipality_id' => $municipality->id,
        'key' => 'time_range_test',
        'type' => MunicipalityVariableType::TimeRange,
        'value' => ['start' => '09:00', 'end' => '17:00'],
        'is_default' => false,
    ]);
    expect($timeRangeVariable->formatted_value)->toBe(['start' => '09:00', 'end' => '17:00']);

    // DateTime range type - should accept properly formatted datetime ranges
    $dateTimeRangeVariable = MunicipalityVariable::factory()->create([
        'municipality_id' => $municipality->id,
        'key' => 'datetime_range_test',
        'type' => MunicipalityVariableType::DateTimeRange,
        'value' => ['start' => '2024-01-01 09:00:00', 'end' => '2024-12-31 17:00:00'],
        'is_default' => false,
    ]);
    expect($dateTimeRangeVariable->formatted_value)->toBe(['start' => '2024-01-01 09:00:00', 'end' => '2024-12-31 17:00:00']);
});

test('custom variables are soft-deletable', function () {
    // arrange
    $municipality = Municipality::factory()->create();
    $municipalityAdmin = User::factory()->create([
        'email' => 'advisor@example.com',
        'role' => Role::ReviewerMunicipalityAdmin,
    ]);
    $municipalityAdmin->municipalities()->attach($municipality);

    $customVariable = MunicipalityVariable::factory()->create([
        'municipality_id' => $municipality->id,
        'name' => 'Custom Variable',
        'key' => 'custom_var',
        'type' => MunicipalityVariableType::Text,
        'value' => 'custom value',
        'is_default' => false,
    ]);

    // act: municipality admin can delete custom variable
    expect($municipalityAdmin->can('delete', $customVariable))->toBe(true);

    $customVariable->delete();

    // assert: variable is soft deleted
    expect($customVariable->fresh()->trashed())->toBe(true);
    expect(MunicipalityVariable::withTrashed()->find($customVariable->id))->not->toBeNull();
    expect(MunicipalityVariable::find($customVariable->id))->toBeNull();
});

test('default variables can be soft-deleted by admin', function () {
    // arrange
    $admin = User::factory()->create([
        'email' => 'admin@example.com',
        'role' => Role::Admin,
    ]);
    $municipality1 = Municipality::factory()->create();
    $municipality2 = Municipality::factory()->create();

    // Create default variables in both municipalities
    $defaultVar1 = MunicipalityVariable::factory()->create([
        'municipality_id' => $municipality1->id,
        'name' => 'Default Variable',
        'key' => 'default_var',
        'type' => MunicipalityVariableType::Text,
        'value' => 'default value',
        'is_default' => true,
    ]);

    $defaultVar2 = MunicipalityVariable::factory()->create([
        'municipality_id' => $municipality2->id,
        'name' => 'Default Variable',
        'key' => 'default_var',
        'type' => MunicipalityVariableType::Text,
        'value' => 'default value',
        'is_default' => true,
    ]);

    // act: admin can delete default variables
    expect($admin->can('delete', $defaultVar1))->toBe(true);
    expect($admin->can('delete', $defaultVar2))->toBe(true);

    // When admin deletes a default variable template, all instances should be deleted
    $defaultVar1->delete();
    $defaultVar2->delete();

    // assert: all default variables are soft-deleted
    expect($defaultVar1->fresh()->trashed())->toBe(true);
    expect($defaultVar2->fresh()->trashed())->toBe(true);

    // Municipality admin cannot delete default variables
    $municipalityAdmin = User::factory()->create([
        'email' => 'advisor@example.com',
        'role' => Role::ReviewerMunicipalityAdmin,
    ]);
    $municipalityAdmin->municipalities()->attach($municipality1);

    $anotherDefaultVar = MunicipalityVariable::factory()->create([
        'municipality_id' => $municipality1->id,
        'name' => 'Another Default Variable',
        'key' => 'another_default',
        'type' => MunicipalityVariableType::Text,
        'value' => 'another default value',
        'is_default' => true,
    ]);

    expect($municipalityAdmin->can('delete', $anotherDefaultVar))->toBe(false);
});

test('admin can not create report question variables as default variables', function () {
    // arrange
    $admin = User::factory()->create([
        'email' => 'admin@example.com',
        'role' => Role::Admin,
    ]);
    $municipality = Municipality::factory()->create();

    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs($admin);

    livewire(CreateMunicipalityVariable::class)
        ->fillForm([
            'name' => 'Report Question 1',
            'key' => 'report_question_1',
            'type' => MunicipalityVariableType::ReportQuestion,
            'value' => 'Is this a test?',
        ])->call('create')
        ->assertHasFormErrors(['type']);
});

test('municipality admin can create report question variable', function () {
    $municipality = Municipality::factory()->create();
    $municipalityAdmin = User::factory()->create([
        'email' => 'advisor@example.com',
        'role' => Role::ReviewerMunicipalityAdmin,
    ]);
    $municipalityAdmin->municipalities()->attach($municipality);

    expect($municipalityAdmin->can('create', MunicipalityVariable::class))->toBe(true);

    Filament::setCurrentPanel(Filament::getPanel('municipality'));
    $this->actingAs($municipalityAdmin);
    Filament::setTenant($municipality);
    // make sure panel is booted with tenant, otherwise observers and scopes arent applied
    Filament::bootCurrentPanel();

    livewire(PagesCreateMunicipalityVariable::class, [
        'tenantRecord' => $municipality,
    ])
        ->assertSchemaExists('form')
        ->fillForm([
            'name' => 'Report Question 1',
            'type' => MunicipalityVariableType::ReportQuestion,
            'value' => 'Is this a test?',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseCount('municipality_variables', 1);

});

test('municipality admin cannot create more then 5 variables of type report question', function () {
    $municipality = Municipality::factory()->create();
    $municipalityAdmin = User::factory()->create([
        'email' => 'advisor@example.com',
        'role' => Role::ReviewerMunicipalityAdmin,
    ]);
    $municipalityAdmin->municipalities()->attach($municipality);

    foreach (range(1, 5) as $i) {
        MunicipalityVariable::factory()->create([
            'municipality_id' => $municipality->id,
            'type' => MunicipalityVariableType::ReportQuestion,
            'key' => 'report_question_'.$i,
        ]);
    }

    Filament::setCurrentPanel(Filament::getPanel('municipality'));
    $this->actingAs($municipalityAdmin);
    Filament::setTenant($municipality);
    // make sure panel is booted with tenant, otherwise observers and scopes arent applied
    Filament::bootCurrentPanel();

    livewire(PagesCreateMunicipalityVariable::class, [
        'tenantRecord' => $municipality,
    ])
        ->assertSchemaExists('form')
        ->fillForm([
            'name' => 'Report Question 6',
            'type' => MunicipalityVariableType::ReportQuestion,
            'value' => 'Is this a test?',
        ])
        ->call('create')
        ->assertHasFormErrors();

    $this->assertDatabaseCount('municipality_variables', 5);

});
