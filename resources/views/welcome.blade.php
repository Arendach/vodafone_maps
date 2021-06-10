<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Document</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.1/dist/css/bootstrap.min.css" rel="stylesheet"
          integrity="sha384-+0n0xVW2eSR5OomGNYDnhzAbDsOXxcvSN1TPprVMTNDbiYZCxYbOOl7+AMvyTG2x" crossorigin="anonymous">
</head>
<body>

<form class="container" style="margin-top: 30px" action="{{ route('merge') }}" method="post" enctype="multipart/form-data">
    <div class="mb-3">
        <label for="file">Файли</label>
        <input type="file" class="form-control" multiple name="kmls[]">
    </div>

    @csrf

    <div class="mb-3">
        <button class="btn btn-primary">
            Обробка
        </button>
    </div>
</form>

</body>
</html>
