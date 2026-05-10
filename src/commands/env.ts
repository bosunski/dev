import { Command, Args } from '@oclif/core'
import { UserException } from '../exceptions.js'

const HOOKBOOK = `# Hookbook (https://github.com/Shopify/hookbook)
__hookbook_shell="$(\ps -p $$ | \awk 'NR > 1 { sub(/^-/, "", $4); print $4 }')"
__hookbook_shellname="$(basename "\${__hookbook_shell}")"

__hookbook_array_contains() {
  [[ "$#" -lt 2 ]] && \\return 1
  \local seeking="$1"; \shift
  \local check="$1"; \shift
  [[ "\${seeking}" == "\${check}" ]] && \\return 0
  __hookbook_array_contains "\${seeking}" "$@"
}

__hookbook_call_each() {
  [[ "$#" -lt 2 ]] && \\return
  \local hookname="$1"; \shift
  \local fn="$1"; \shift
  "\${fn}" "\${hookname}"
  __hookbook_call_each "\${hookname}" "$@"
}

[[ "\${__hookbook_shellname}" == "zsh" ]] && {
  hookbook_add_hook() {
    \local fn="$1"
    \eval "
      __hookbook_\${fn}_preexec() { \${fn} preexec }
      __hookbook_\${fn}_precmd()  { \${fn} precmd }
    "
    __hookbook_array_contains "__hookbook_\${fn}_preexec" "\${preexec_functions[@]}" \\
      || preexec_functions+=("__hookbook_\${fn}_preexec")
    __hookbook_array_contains "__hookbook_\${fn}_precmd" "\${precmd_functions[@]}" \\
      || precmd_functions+=("__hookbook_\${fn}_precmd")
  }
}
\\unset __hookbook_shell __hookbook_shellname`

export default class Env extends Command {
  static id = 'env'
  static description = 'Initialize preexec hook for BASH, FISH or ZSH'
  static args = {
    shell: Args.string({
      description: 'The shell to initialize the hook for',
      default: 'zsh',
      options: ['zsh', 'bash', 'fish'],
    }),
  }

  async run(): Promise<void> {
    const { args } = await this.parse(Env)
    const shell = args.shell as string
    const self = process.execPath

    switch (shell) {
      case 'zsh':
        this.log(this.zshHook(self))
        break
      case 'bash':
        this.log(this.bashHook(self))
        break
      case 'fish':
        this.log(this.fishHook(self))
        break
      default:
        throw new UserException(`Unsupported shell. Supported shells are: zsh, bash, fish`)
    }
  }

  private zshHook(self: string): string {
    return `__dev_hook() {
  local flags; flags=()
  if [[ "$1" == "preexec" ]]; then
    return
  fi
  "${self}" hook "\${flags[@]}"
}

${HOOKBOOK}

__dev_force_run=1
hookbook_add_hook __dev_hook`
  }

  private bashHook(self: string): string {
    return `__dev_hook() {
  local flags; flags=(--shellpid "$$")
  eval "$("${self}" hook "\${flags[@]}")"
}

${HOOKBOOK}

__dev_force_run=1
hookbook_add_hook __dev_hook`
  }

  private fishHook(self: string): string {
    return `function __dev_hook --on-event fish_prompt --on-variable PWD
  set -l flags --fish
  if [ -n "$__dev_force_run" ];
    set -a flags --force
    set -eg __dev_force_run
  end
  "${self}" hook $flags | source 2>/dev/null
end

set -g __dev_force_run 1`
  }
}
