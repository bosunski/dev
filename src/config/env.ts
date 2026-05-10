import { Value } from './value.js'
import type { RawEnvValue } from '../types/config.js'

export class Env {
  private resolved = false
  private prompted: Record<string, string> = {}
  private envWasPrompted = false
  private env: Map<string, string>

  constructor(
    rawEnv: Record<string, RawEnvValue>,
    private readonly substitutions: Record<string, string> = {},
  ) {
    this.env = new Map(Object.entries(rawEnv).map(([k, v]) => [k, typeof v === 'string' ? v : '']))
    // Store raw values for resolution
    this._raw = rawEnv
  }

  private _raw: Record<string, RawEnvValue>

  async resolve(prompted: Record<string, string> = {}): Promise<[Map<string, string>, Record<string, string>]> {
    if (this.resolved) {
      return [this.env, this.prompted]
    }

    for (const [key, rawValue] of Object.entries(this._raw)) {
      const value = Value.from(rawValue)

      if (value.shouldPrompt() && key in prompted) {
        const resolved = prompted[key]!
        this.env.set(key, resolved)
        this.prompted[key] = resolved
      } else {
        const resolved = await this.resolveValue(key, value)
        this.env.set(key, resolved)
      }
    }

    this.resolved = true
    return [this.env, this.prompted]
  }

  private async resolveValue(key: string, value: Value): Promise<string> {
    const subs = new Map(Object.entries(this.substitutions))
    const resolved = await value.resolve(subs)

    if (value.wasPrompted()) {
      this.envWasPrompted = true
      this.prompted[key] = resolved
    }

    return resolved
  }

  put(key: string, value: string): void {
    this.env.set(key, value)
    this._raw[key] = value
  }

  wasPrompted(): boolean {
    return this.envWasPrompted
  }
}
