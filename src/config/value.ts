import type { IOInterface } from '../types/io.js'
import type { PromptArgs, RawEnvValue } from '../types/config.js'
import { UserException } from '../exceptions.js'

export class Value {
  private static io: IOInterface

  private _prompted = false

  constructor(private value: RawEnvValue) {}

  static from(value: RawEnvValue): Value {
    return new Value(value)
  }

  static setIO(io: IOInterface): void {
    Value.io = io
  }

  wasPrompted(): boolean {
    return this._prompted
  }

  shouldPrompt(): boolean {
    if (typeof this.value === 'object' && this.value !== null) return true
    if (typeof this.value !== 'string') return false
    return /^\$PROMPT\([^)]*\)$/.test(this.value)
  }

  async resolve(substitutions: Map<string, string> = new Map()): Promise<string> {
    if (typeof this.value === 'object' && this.value !== null) {
      return this.prompt(this.value as PromptArgs)
    }

    // Coerce numbers/booleans to strings
    if (typeof this.value !== 'string') {
      return String(this.value)
    }

    if (!this.value) return this.value

    await this.applySubstitutions(substitutions)
    await this.evaluate()
    await this.parsePrompts()

    return this.value as string
  }

  private async prompt(args: PromptArgs): Promise<string> {
    const type = args.type ?? 'text'
    this._prompted = true

    if (type === 'password') {
      return Value.io.password(
        args.prompt,
        args.placeholder ?? '',
        args.required ?? true,
        null,
        args.hint ?? '',
      )
    }

    return Value.io.text(
      args.prompt,
      args.placeholder ?? '',
      args.default ?? '',
      args.required ?? true,
      null,
      args.hint ?? '',
    )
  }

  private async applySubstitutions(substitutions: Map<string, string>): Promise<void> {
    if (typeof this.value !== 'string' || substitutions.size === 0) return

    const matches = [...(this.value as string).matchAll(/\${([^}]*)}/g)]
    for (const match of matches) {
      const key = match[1]!
      const replacement = substitutions.get(key)
      if (replacement) {
        this.value = (this.value as string).replace('${' + key + '}', replacement)
      }
    }
  }

  private async evaluate(): Promise<void> {
    if (typeof this.value !== 'string') return

    const matches = [...(this.value as string).matchAll(/`([^`]*)`/g)]
    for (const match of matches) {
      const cmd = match[1]!
      const proc = Bun.spawn(['sh', '-c', cmd], { stdout: 'pipe', stderr: 'pipe' })
      const exitCode = await proc.exited
      if (exitCode !== 0) {
        throw new UserException(`Failed to evaluate environment variable: ${this.value}`)
      }

      const output = (await new Response(proc.stdout).text()).trimEnd()
      this.value = (this.value as string).replace('`' + cmd + '`', output)
    }
  }

  private async parsePrompts(): Promise<void> {
    if (typeof this.value !== 'string') return

    const matches = [...(this.value as string).matchAll(/^\$PROMPT\(([^)]*)\)$/gm)]
    for (const match of matches) {
      const inner = match[1]!
      const args = inner.split(':')
      const type = args[0] as 'password' | 'text'

      if (type !== 'password' && type !== 'text') {
        throw new UserException(`Unknown prompt type: ${type}`)
      }

      const promptArgs: PromptArgs = {
        type,
        prompt: args[1] ?? '',
        placeholder: args[2] ?? '',
        default: args[3] ?? '',
        required: Boolean(args[4] ?? false),
        hint: args[5] ?? '',
      }

      const resolved = await this.prompt(promptArgs)
      this.value = (this.value as string).replace(`$PROMPT(${inner})`, resolved)
    }
  }
}
