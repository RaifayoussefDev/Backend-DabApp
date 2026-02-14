$lastEvents = \App\Models\Event::withTrashed()->latest()->take(5)->get();
echo "Last 5 Events:\n";
foreach($lastEvents as $e) {
echo "ID: " . $e->id . " - " . $e->title . " (Status: " . $e->status . ")\n";
}