try {
$e = \App\Models\Event::withTrashed()->find(30);
if($e) {
echo "Event 30 FOUND. ID: " . $e->id . "\n";
echo "Status: " . $e->status . "\n";
echo "Deleted At: " . ($e->deleted_at ? $e->deleted_at : 'NULL') . "\n";
echo "Is Published: " . ($e->is_published ? 'YES' : 'NO') . "\n";
echo "Organizer ID: " . $e->organizer_id . "\n";
} else {
echo "Event 30 NOT FOUND in DB.\n";
}
} catch(\Exception $err) {
echo "Error: " . $err->getMessage() . "\n";
}