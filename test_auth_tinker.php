$request = Illuminate\Http\Request::create("/api/projects", "GET");
$response = app()->handle($request);
echo "Status: " . $response->getStatusCode() . "`n";
echo "Content: " . $response->getContent() . "`n";
