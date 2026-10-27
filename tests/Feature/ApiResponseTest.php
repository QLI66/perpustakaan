<?php

namespace Tests\Feature;

use App\Exceptions\BusinessRuleException;
use App\Models\User;
use App\Traits\ApiResponder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ApiResponseTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/api/test/success', function () {
            $class = new class
            {
                use ApiResponder;

                public function response()
                {
                    return $this->successResponse(['key' => 'value'], 'Action successful');
                }
            };

            return $class->response();
        });

        Route::post('/api/test/validation', function (Request $request) {
            $request->validate(['name' => 'required']);

            return response()->json(['message' => 'ok']);
        });

        Route::get('/api/test/business-rule', function () {
            throw new BusinessRuleException('Business rule violated.');
        });

        Route::get('/api/test/500', function () {
            throw new \Exception('Unexpected system error.');
        });

        Route::middleware('auth:sanctum')->get('/api/test/401', function () {
            return response()->json(['message' => 'ok']);
        });
    }

    public function test_success_response_structure(): void
    {
        $response = $this->getJson('/api/test/success');

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Action successful',
            'data' => [
                'key' => 'value',
            ],
        ]);
    }

    public function test_validation_response_structure(): void
    {
        $response = $this->postJson('/api/test/validation', []);

        $response->assertStatus(422);
        $response->assertJsonStructure([
            'success',
            'message',
            'errors' => [
                'name',
            ],
        ]);
        $this->assertFalse($response->json('success'));
        $this->assertEquals('Validation failed.', $response->json('message'));
    }

    public function test_401_response_structure(): void
    {
        $response = $this->getJson('/api/test/401');

        $response->assertStatus(401);
        $response->assertJson([
            'success' => false,
            'message' => 'Unauthenticated.',
        ]);
    }

    public function test_404_response_structure(): void
    {
        $response = $this->getJson('/api/test/non-existent-route');

        $response->assertStatus(404);
        $response->assertJson([
            'success' => false,
            'message' => 'Resource not found.',
        ]);
    }

    public function test_business_rule_exception_response_structure(): void
    {
        $response = $this->getJson('/api/test/business-rule');

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'message' => 'Business rule violated.',
        ]);
    }

    public function test_production_response_does_not_expose_stack_trace(): void
    {
        // Even when APP_DEBUG=true in tests, our generic Exception handler overrides it
        // and returns our generic 500 format. Let's verify this.
        $response = $this->getJson('/api/test/500');

        $response->assertStatus(500);
        $response->assertJson([
            'success' => false,
            'message' => 'Internal Server Error',
        ]);

        $this->assertArrayNotHasKey('exception', $response->json());
        $this->assertArrayNotHasKey('trace', $response->json());
    }

    public function test_password_is_never_serialized(): void
    {
        $user = User::factory()->create(['password' => 'secret123']);
        $this->assertArrayNotHasKey('password', $user->toArray());

        $decoded = json_decode($user->toJson(), true);
        $this->assertArrayNotHasKey('password', $decoded);
    }
}
