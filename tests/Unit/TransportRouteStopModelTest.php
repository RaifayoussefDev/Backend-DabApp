<?php

namespace Tests\Unit;

use App\Models\TransportRoute;
use App\Models\TransportRouteStop;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Tests\TestCase;

/**
 * app/Models/TransportRouteStop.php used to declare a second TransportRoute class, so TransportRouteStop
 * did not exist and every stop lookup/creation failed with "Class not found".
 */
class TransportRouteStopModelTest extends TestCase
{
    public function test_stop_model_maps_its_table(): void
    {
        $this->assertTrue(class_exists(TransportRouteStop::class));
        $this->assertSame('transport_route_stops', (new TransportRouteStop())->getTable());
    }

    public function test_route_and_stop_relations_point_at_each_other(): void
    {
        $stops = (new TransportRoute())->stops();
        $this->assertInstanceOf(HasMany::class, $stops);
        $this->assertInstanceOf(TransportRouteStop::class, $stops->getRelated());
        $this->assertSame('route_id', $stops->getForeignKeyName());

        $route = (new TransportRouteStop())->route();
        $this->assertInstanceOf(BelongsTo::class, $route);
        $this->assertInstanceOf(TransportRoute::class, $route->getRelated());
    }
}
