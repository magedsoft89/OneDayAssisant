<!DOCTYPE html>
<html>
<head>
    <title> OneDay Server</title>
</head>
<body>
@if(isset($details['image']) && $details['image'])
    <img alt="" src="{{ $message->embed($details['image']) }}">
@endif
<h2> Hello {{ $details['username'] }}</h2>
<h1> {{ $details['title'] }}  </h1>
<p>{!!  $details['content']!!} </p>
<p>Thank you</p>
</body>
</html>
