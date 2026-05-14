import { existsSync, mkdirSync, writeFileSync, unlinkSync } from 'node:fs'
import { join } from 'node:path'
import type { Dev } from '../dev.js'
import type { RawServe, RawServeProcess } from '../types/config.js'

const COLORS = [2, 3, 4, 5, 6, 42, 130, 103, 129, 108]

type ProcessEntry = {
  name: string
  command: string
  cwd: string
  env: Record<string, string>
  color: number
}

function colorPrefix(name: string, color: number): string {
  return `\x1b[38;5;${color}m${name.padEnd(20)}\x1b[0m | `
}

function isServeProcess(value: RawServe): value is RawServeProcess {
  return typeof value === 'string' || ('run' in value && typeof value.run === 'string')
}

export class ServeManager {
  private processes: Bun.Subprocess[] = []
  private interrupted = false

  constructor(private readonly dev: Dev) {}

  getGroups(dev: Dev): string[] {
    const serve = dev.config.getServe()
    if (!serve || typeof serve === 'string') return []
    return Object.entries(serve)
      .filter(([, v]) => !isServeProcess(v))
      .map(([k]) => k)
  }

  async run(groups?: string[]): Promise<boolean> {
    const entries = await this.collectProcesses(groups)

    if (entries.length === 0) {
      this.dev.io().dev('No processes to run. You can register processes under serve in the dev.yml file.')
      return false
    }

    this.storePid()

    try {
      return await this.runAll(entries)
    } finally {
      this.removePid()
    }
  }

  private async collectProcesses(groups?: string[]): Promise<ProcessEntry[]> {
    const raw: ProcessEntry[] = []
    await this.collectFrom(this.dev, raw, undefined, groups)

    // Deduplicate by name — first-seen wins
    const seen = new Set<string>()
    return raw.filter(e => {
      if (seen.has(e.name)) return false
      seen.add(e.name)
      return true
    })
  }

  private async collectFrom(dev: Dev, entries: ProcessEntry[], parentName?: string, groups?: string[]): Promise<void> {
    // Collect from dependency projects first
    const projects = dev.config.projects()
    for (const projectDef of projects) {
      try {
        const { Config } = await import('../config/config.js')
        const depConfig = Config.fromProjectDefinition(projectDef)
        const { Runner } = await import('../execution/runner.js')
        const { Repository } = await import('../execution/repository.js')
        const { StdIO } = await import('../io/stdio.js')
        const io = new StdIO()
        const repo = new Repository()
        const runner = new Runner(depConfig, io, repo)
        const { Dev: DevClass } = await import('../dev.js')
        const depDev = new DevClass(depConfig, runner, io)
        depDev.setPluginManager(dev.getPluginManager())
        await this.collectFrom(depDev, entries, projectDef.repo, groups)
      } catch {
        // Skip if project not found
      }
    }

    const serve = dev.config.getServe()
    if (!serve || (typeof serve === 'object' && Object.keys(serve).length === 0)) return

    const serves: Record<string, RawServe> =
      typeof serve === 'string' ? { serve: serve } : serve

    const projectName = dev.config.getName() || dev.config.projectName()
    const multiProject = !!parentName

    for (const [name, rawServe] of Object.entries(serves)) {
      if (isServeProcess(rawServe)) {
        // Flat serves always run — they are the shared layer
        const serveConfig = typeof rawServe === 'string' ? { run: rawServe } : rawServe
        const displayName = multiProject ? `${projectName}:${name}` : name
        this.pushEntry(entries, dev, displayName, serveConfig)
      } else {
        // rawServe is a RawServeGroup — include all groups or only the requested ones
        if (groups && !groups.includes(name)) continue
        for (const [subName, subServe] of Object.entries(rawServe)) {
          const serveConfig = typeof subServe === 'string' ? { run: subServe } : subServe
          const displayName = multiProject ? `${projectName}:${name}:${subName}` : `${name}:${subName}`
          this.pushEntry(entries, dev, displayName, serveConfig)
        }
      }
    }
  }

  private pushEntry(
    entries: ProcessEntry[],
    dev: Dev,
    displayName: string,
    serveConfig: { run: string; env?: string | false; cwd?: string },
  ): void {
    const env = this.resolveDotenv(dev, serveConfig.env)
    const cwd = serveConfig.cwd
      ? join(dev.config.cwd(), serveConfig.cwd)
      : dev.config.cwd()

    entries.push({
      name: displayName.toLowerCase(),
      command: serveConfig.run,
      cwd,
      env,
      color: COLORS[entries.length % COLORS.length]!,
    })
  }

  private resolveDotenv(dev: Dev, envFile: string | false | undefined): Record<string, string> {
    if (envFile === false) return {}

    const file = envFile === undefined || envFile === '.env' ? '.env' : `.env.${envFile}`
    const path = dev.config.cwd(file)

    if (!existsSync(path)) return {}

    try {
      const content = Bun.file(path).text()
      const env: Record<string, string> = {}
      // Simple .env parser
      const text = Bun.readableStreamToText(Bun.file(path).stream()) as unknown as string
      return env
    } catch {
      return {}
    }
  }

  private parseDotenv(content: string): Record<string, string> {
    const env: Record<string, string> = {}
    for (const line of content.split('\n')) {
      const trimmed = line.trim()
      if (!trimmed || trimmed.startsWith('#')) continue
      const eqIdx = trimmed.indexOf('=')
      if (eqIdx === -1) continue
      const key = trimmed.slice(0, eqIdx).trim()
      let val = trimmed.slice(eqIdx + 1).trim()
      if ((val.startsWith('"') && val.endsWith('"')) || (val.startsWith("'") && val.endsWith("'"))) {
        val = val.slice(1, -1)
      }
      env[key] = val
    }
    return env
  }

  private async runAll(entries: ProcessEntry[]): Promise<boolean> {
    const showPrefix = entries.length > 1

    const procs: Array<{ proc: Bun.Subprocess; entry: ProcessEntry }> = []

    for (const entry of entries) {
      process.stdout.write(`${colorPrefix(entry.name, entry.color)}\x1b[1mRunning...\x1b[0m\n`)

      const dotenvPath = join(entry.cwd, '.env')
      let dotenv: Record<string, string> = {}
      if (existsSync(dotenvPath)) {
        const content = await Bun.file(dotenvPath).text()
        dotenv = this.parseDotenv(content)
      }

      const proc = Bun.spawn(['sh', '-c', entry.command], {
        cwd: entry.cwd,
        env: {
          FORCE_COLOR: '1',
          TERM: 'xterm-256color',
          ...dotenv,
          ...entry.env,
          ...process.env as Record<string, string>,
        },
        stdout: 'pipe',
        stderr: 'pipe',
        stdin: 'inherit',
      })

      procs.push({ proc, entry })

      // Stream output with color prefix
      void this.pipeOutput(proc.stdout, entry.name, entry.color)
      void this.pipeOutput(proc.stderr, entry.name, entry.color)
    }

    this.processes = procs.map(p => p.proc)

    // Set up signal handlers
    const cleanup = async () => {
      if (this.interrupted) return
      this.interrupted = true
      await this.shutdownAll(procs.map(p => p.proc))
      process.exit(0)
    }

    process.on('SIGINT', () => void cleanup())
    process.on('SIGTERM', () => void cleanup())
    process.on('SIGHUP', () => void cleanup())

    // Wait for any process to exit or interrupt
    const exitPromises = procs.map(({ proc, entry }) =>
      proc.exited.then(code => {
        process.stdout.write(`${colorPrefix(entry.name, entry.color)}\x1b[2mexited with code ${code}\x1b[0m\n`)
        if (!this.interrupted) void cleanup()
      }),
    )

    await Promise.race(exitPromises)
    await Promise.all(exitPromises)

    return true
  }

  private async pipeOutput(stream: ReadableStream<Uint8Array> | null, name: string, color: number): Promise<void> {
    if (!stream) return
    const prefix = colorPrefix(name, color)
    const decoder = new TextDecoder()

    try {
      const reader = stream.getReader()
      let buffer = ''

      while (true) {
        const { done, value } = await reader.read()
        if (done) break

        buffer += decoder.decode(value, { stream: true })
        const lines = buffer.split('\n')
        buffer = lines.pop() ?? ''

        for (const line of lines) {
          process.stdout.write(prefix + line + '\n')
        }
      }

      if (buffer) process.stdout.write(prefix + buffer + '\n')
    } catch {
      // Process ended
    }
  }

  private async shutdownAll(procs: Bun.Subprocess[]): Promise<void> {
    // Send SIGTERM to all
    for (const proc of procs) {
      try { proc.kill(15) } catch {}
    }

    // Give 5 seconds for graceful shutdown
    const timeout = new Promise<void>(resolve => setTimeout(resolve, 5000))
    const waitAll = Promise.all(procs.map(p => p.exited.catch(() => {})))
    await Promise.race([timeout, waitAll])

    // Force kill any still running
    for (const proc of procs) {
      try { proc.kill(9) } catch {}
    }
  }

  private storePid(): void {
    const dir = this.dev.config.path()
    if (!existsSync(dir)) mkdirSync(dir, { recursive: true })
    writeFileSync(join(dir, this.dev.name), String(process.pid))
  }

  private removePid(): void {
    const pidFile = join(this.dev.config.path(), this.dev.name)
    if (existsSync(pidFile)) {
      try { unlinkSync(pidFile) } catch {}
    }
  }
}
