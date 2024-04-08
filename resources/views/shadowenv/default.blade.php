@foreach ($paths as $path)
(env/prepend-to-pathlist "PATH" "{{ $path }}")
@endforeach

@foreach ($envs as $name => $value)
(env/set "{{ $name }}" "{{$value }}")
@endforeach
