$request = Illuminate\Http\Request::create("/api/transactions", "GET", ["project_id" => 1]);
$request->headers->set("Accept", "application/json");
$user = App\Models\User::first();
$request->setUserResolver(function () use ($user) { return $user; });
$response = app()->handle($request);
echo "Status: " . $response->getStatusCode() . "\n";
echo "Content: " . substr($response->getContent(), 0, 500) . "\n";
