__dev_hook() {
    local flags; flags=(--shellpid "$$")
    if [[ "$1" == "preexec" ]]; then
      {{-- flags+=(--silent) --}}
    fi
    {{-- if [[ -n $__dev_force_run ]]; then
      flags+=(--force)
      unset __dev_force_run
    fi --}}
    eval "$("{{$self}}" hook "${flags[@]}")"
  }

  @include('env.hookbook')

  __dev_force_run=1
  hookbook_add_hook __dev_hook
