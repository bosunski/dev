function __dev_hook --on-event fish_prompt --on-variable PWD
  set -l flags --fish
  if [ -n "$__dev_force_run" ];
    set -a flags --force
    set -eg __dev_force_run
  end
  {{$self}} hook $flags | source 2>/dev/null
end

set -g __dev_force_run 1
