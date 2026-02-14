try {
// Ensure role exists
$role = \App\Models\Role::find(1);
if(!$role) {
echo "Role 1 not found. Creating...\n";
$role = new \App\Models\Role();
$role->id = 1;
$role->name = 'admin';
$role->save();
}

// Ensure category 1 exists
$cat = \App\Models\EventCategory::find(1);
if(!$cat) {
$cat = new \App\Models\EventCategory();
$cat->id = 1;
$cat->name = "Test Category";
$cat->slug = "test-category";
$cat->save();
echo "Created Test Category 1.\n";
}

// Ensure user 999 exists
$user = \App\Models\User::find(999);
if(!$user) {
$user = new \App\Models\User();
$user->id = 999;
$user->email = "test999@example.com";
$user->password = bcrypt('password');
$user->first_name = "Test";
$user->last_name = "User";
$user->role_id = 1;
$user->save();
echo "Created Test User 999.\n";
}

// Create Event 30 if not exists
$e = \App\Models\Event::withTrashed()->find(30);
if(!$e) {
$e = new \App\Models\Event();
$e->id = 30;
$e->title = "Test Event 30";
$e->slug = "test-event-30";
$e->description = "Test Description";
$e->event_date = "2024-12-12";
$e->start_time = "10:00:00";
$e->category_id = 1;
$e->status = 'draft';
$e->is_published = 0;
$e->organizer_id = 999;
$e->save();
echo "Event 30 CREATED as DRAFT.\n";
} else {
echo "Event 30 ALREADY EXISTS.\n";
if($e->deleted_at) {
echo "Event 30 is DELETED. Restoring for test...\n";
$e->restore();
}
}

// Now try to find it using the controller logic
$query = \App\Models\Event::with(['category', 'organizer'])->withCount('interests');
$found = $query->where('id', 30)->first();

if ($found) {
echo "SUCCESS: Controller logic FOUND the event.\n";
echo "Event Status: " . $found->status . "\n";
echo "Is Published: " . $found->is_published . "\n";
} else {
echo "FAILURE: Controller logic DID NOT find the event.\n";
}

} catch(\Exception $err) {
echo "Error: " . $err->getMessage() . "\n";
}