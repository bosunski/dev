import { existsSync } from 'node:fs'
import { join } from 'node:path'
import { Config } from '../config/config.js'
import type { IOInterface } from '../types/io.js'
import type { Step } from '../types/step.js'
import type { Repository } from './repository.js'
import { UserException } from '../exceptions.js'

export type Shell = { name: string; bin: string; profile: string }

const STEP_COLORS = [2, 3, 4, 5, 6, 42, 130, 103, 129, 108]
let colorIndex = 0

export class Runner {
  private envResolver: (() => Promise<Map<string, string>>) | null = null
  private usingShadowEnv = true
  private _shell: Shell | null = null
  private stepColor: number = STEP_COLORS[colorIndex++ % STEP_COLORS.length]!
  private stepPrefix: string = ''

  constructor(
    public readonly config: Config,
    private readonly io: IOInterface,
    private readonly stepRepository: Repository,
  ) {}

  withoutShadowEnv(): Runner {
    const r = this.clone()
    r.usingShadowEnv = false
    return r
  }

  withoutEnv(): Runner {
    const r = this.clone()
    r.envResolver = null
    return r
  }

  private clone(): Runner {
    const r = new Runner(this.config, this.io, this.stepRepository)
    r.envResolver = this.envResolver
    r.usingShadowEnv = this.usingShadowEnv
    r._shell = this._shell
    r.stepColor = this.stepColor
    r.stepPrefix = this.stepPrefix
    return r
  }

  setEnvResolver(resolver: () => Promise<Map<string, string>>): void {
    this.envResolver = resolver
  }

  async execute(steps: Step | Step[], force = false): Promise<boolean> {
    const arr = Array.isArray(steps) ? steps : [steps]

    for (const step of arr) {
      const id = step.id()
      if (this.stepRepository.steps[id]) continue

      this.stepRepository.steps[id] = step
      if (!(await this.executeStep(step, force))) return false
    }

    return true
  }

  lastFailedStep: Step | null = null

  private async executeStep(step: Step, force = false): Promise<boolean> {
    if (!force && (await step.done(this))) return true

    const name = step.name()
    if (name) {
      this.stepPrefix = name
      const stdio = this.io as { startStepBuffer?: (c: number) => void }
      stdio.startStepBuffer?.(this.stepColor)
      this.io.stepStart(name)
    }

    const ok = await step.run(this)

    if (name) this.io.stepEnd(name, ok)

    // Only record the first (deepest) failure — don't overwrite with an outer composite step
    if (!ok && !this.lastFailedStep) this.lastFailedStep = step
    return ok
  }

  async exec(command: string | string[], path?: string, env: Record<string, string> = {}): Promise<boolean> {
    const proc = await this.spawnRaw(command, path, env, true)
    const code = await proc.exited
    return code === 0
  }

  async spawn(command: string | string[], path?: string, env: Record<string, string> = {}): Promise<Bun.Subprocess> {
    return this.spawnRaw(command, path, env, false)
  }

  private async spawnRaw(
    command: string | string[],
    path?: string,
    env: Record<string, string> = {},
    buffered = false,
  ): Promise<Bun.Subprocess> {
    const fullEnv = await this.environment(env)
    const finalCmd = this.createShadowEnvCommand(command)

    if (buffered) {
      const proc = Bun.spawn(finalCmd, {
        cwd: path ?? this.config.cwd(),
        env: fullEnv,
        stdin: 'inherit',
        stdout: 'pipe',
        stderr: 'pipe',
      })

      void this.bufferStream(proc.stdout)
      void this.bufferStream(proc.stderr)

      return proc
    }

    return Bun.spawn(finalCmd, {
      cwd: path ?? this.config.cwd(),
      env: fullEnv,
      stdin: 'inherit',
      stdout: 'inherit',
      stderr: 'inherit',
    })
  }

  private async bufferStream(stream: ReadableStream<Uint8Array> | null): Promise<void> {
    if (!stream) return
    const decoder = new TextDecoder()
    const reader = stream.getReader()
    const stdio = this.io as { appendStepBuffer?: (line: string) => void }
    let buf = ''
    try {
      while (true) {
        const { done, value } = await reader.read()
        if (done) break
        buf += decoder.decode(value, { stream: true })
        const lines = buf.split('\n')
        buf = lines.pop() ?? ''
        for (const line of lines) {
          if (line.trim()) stdio.appendStepBuffer?.(line)
        }
      }
      if (buf.trim()) stdio.appendStepBuffer?.(buf)
    } catch { /* process ended */ }
  }

  private async environment(extra: Record<string, string> = {}): Promise<Record<string, string>> {
    const configEnvs = await this.config.envs()
    const pluginEnvs = this.envResolver ? await this.envResolver() : new Map<string, string>()

    return {
      ...Object.fromEntries(Object.entries(process.env).filter(([, v]) => v !== undefined) as [string, string][]),
      ...Object.fromEntries(configEnvs),
      ...Object.fromEntries(pluginEnvs),
      ...extra,
    }
  }

  createShadowEnvCommand(command: string | string[]): string[] {
    if (!this.usingShadowEnv) {
      return typeof command === 'string' ? ['sh', '-c', command] : command
    }

    this.checkShadowEnv()

    const shell = process.env['SHELL'] ?? '/bin/sh'
    const opts = this.config.isDebug() ? '-ecv' : '-ec'
    const cmd = Array.isArray(command) ? command.join(' ') : command

    return [this.shadowenvBin(), 'exec', '--', shell, opts, cmd]
  }

  private _shadowEnvChecked: boolean | null = null

  checkShadowEnv(force = false): [boolean, boolean] {
    if (!force && this._shadowEnvChecked !== null) {
      return [this._shadowEnvChecked, this._shadowEnvChecked]
    }

    const shell = this.shell(null)
    if (!shell) return [false, false]

    try {
      const result = Bun.spawnSync(
        [shell.bin, '-c', `(source ${shell.profile} && command -v __shadowenv_hook) >/dev/null 2>&1`],
      )
      const hookInstalled = result.exitCode === 0
      this._shadowEnvChecked = hookInstalled
      this.usingShadowEnv = hookInstalled

      if (hookInstalled) return [true, true]

      const binCheck = Bun.spawnSync(['sh', '-c', 'command -v shadowenv'])
      return [false, binCheck.exitCode === 0]
    } catch {
      return [false, false]
    }
  }

  shadowenvBin(): string {
    return this.config.brewPath('bin/shadowenv')
  }

  hasCommand(command: string): boolean {
    const result = Bun.spawnSync(['sh', '-c', `command -v ${command}`])
    return result.exitCode === 0
  }

  shell(defaultBin: string | null = '/bin/bash'): Shell | null {
    if (this._shell) return this._shell

    const bin = process.env['SHELL'] ?? defaultBin
    if (!bin) return null

    const name = bin.split('/').pop()!
    try {
      const profile = this.profile(name)
      this._shell = { name, bin, profile }
      return this._shell
    } catch {
      return null
    }
  }

  private profile(shell: string): string {
    const candidates: string[] = (() => {
      switch (shell) {
        case 'bash': return ['.bash_profile', '.bashrc', 'bash_profile', 'bashrc', '.profile']
        case 'zsh': return ['.zshrc']
        case 'fish': return ['config.fish']
        default: throw new UserException(`Unknown shell: ${shell}. Supported shells are: bash, zsh, fish.`)
      }
    })()

    for (const candidate of candidates) {
      const fullPath = Config.home(candidate)
      if (existsSync(fullPath)) return fullPath
    }

    throw new UserException(
      `Unable to find the profile file for the shell: ${shell}. Supported shells are: bash, zsh, fish.`,
    )
  }

  getIO(): IOInterface {
    return this.io
  }

  path(morePath?: string): string {
    return this.config.path(morePath)
  }
}
