import { readFileSync, existsSync, mkdirSync, writeFileSync } from 'node:fs'
import { join, sep } from 'node:path'
import yaml from 'js-yaml'
import { Env } from './env.js'
import { UpConfig } from './up-config.js'
import { ProjectDefinition } from './project-definition.js'
import type { RawConfig, RawCommand, RawServe, RawServeProcess } from '../types/config.js'
import { UserException } from '../exceptions.js'

export type DevSettings = {
  locks: Record<string, string>
  disabled: string[]
  env: Record<string, string>
}

export class Config {
  static readonly DevDir = '.dev'
  static readonly SrcDir = 'src'
  static readonly DefaultSource = 'github.com'
  static readonly FileName = 'dev.yml'
  static readonly LocalFileName = 'dev.local.yml'
  static readonly LockFiles = [
    'composer.json',
    'package.json',
    'composer.lock',
    'yarn.lock',
    'package-lock.json',
    Config.FileName,
  ]

  settings: DevSettings = {
    locks: {},
    disabled: [],
    env: {},
  }

  private readonly _up: UpConfig
  private _env: Env
  private readonly _uname: string

  constructor(
    private readonly _path: string,
    private readonly raw: RawConfig,
    public isRoot = false,
    public readonly root: string | null = null,
  ) {
    this.readSettings()
    this._up = new UpConfig(raw.steps ?? raw.up ?? [])
    this._env = new Env(raw.env ?? {}, Object.fromEntries(
      Object.entries(process.env).filter(([, v]) => v !== undefined) as [string, string][]
    ))
    this._uname = process.platform === 'darwin' ? 'Darwin' : process.platform === 'linux' ? 'Linux' : 'Windows'
  }

  private readSettings(): void {
    const jsonPath = this.cwd(Config.DevDir + sep + 'config.json')
    if (existsSync(jsonPath)) {
      try {
        const content = readFileSync(jsonPath, 'utf8')
        const parsed = JSON.parse(content) as Partial<DevSettings>
        this.settings = { ...this.settings, ...parsed }
      } catch {
        throw new UserException(`Failed to parse ${jsonPath}. Please check the file for syntax errors.`)
      }
    }
  }

  writeSettings(): void {
    const dir = this.cwd(Config.DevDir)
    if (!existsSync(dir)) mkdirSync(dir, { recursive: true })
    const jsonPath = join(dir, 'config.json')
    writeFileSync(jsonPath, JSON.stringify(this.settings, null, 2))
  }

  getName(): string {
    return this.raw.name ?? ''
  }

  projects(all = false): ProjectDefinition[] {
    return (this.raw.projects ?? [])
      .map(p => new ProjectDefinition(p))
      .filter(p => all || !this.settings.disabled.includes(p.repo))
      .filter(p => {
        if (p.repo === this.projectName()) {
          throw new UserException('You cannot reference the current project in its own config!')
        }
        return true
      })
      .filter((p, i, arr) => arr.findIndex(x => x.repo === p.repo) === i) // unique
  }

  sites(): Record<string, string> {
    return this.raw.sites ?? {}
  }

  commands(): Record<string, RawCommand> {
    return this.raw.commands ?? {}
  }

  up(): UpConfig {
    return this._up
  }

  path(p?: string): string {
    return this.cwd(Config.DevDir + sep + (p ? p.replace(/^[/\\]/, '') : ''))
  }

  projectPath(p?: string): string {
    return this.cwd(
      Config.DevDir + sep + Config.SrcDir + sep + Config.DefaultSource + sep + (p ? p.replace(/^[/\\]/, '') : ''),
    )
  }

  devPath(p?: string): string {
    return this.cwd(Config.DevDir + sep + (p ? p.replace(/^[/\\]/, '') : ''))
  }

  brewPath(p = ''): string {
    if (this.isDarwin()) return join('/opt/homebrew', p)
    if (this.isLinux()) return join('/home/linuxbrew/.linuxbrew', p)
    throw new UserException(`Unsupported OS: ${this._uname}`)
  }

  cwd(p?: string): string {
    if (p) return join(this._path, p.replace(/^[/\\]/, ''))
    return this._path
  }

  globalPath(p?: string): string {
    const base = join(Config.home(), Config.DevDir)
    return p ? join(base, p.replace(/^[/\\]/, '')) : base
  }

  globalBinPath(p?: string): string {
    return this.globalPath(p ? `bin/${p}` : 'bin')
  }

  static home(p?: string): string {
    const home = process.env['HOME'] ?? process.env['USERPROFILE'] ?? ''
    return p ? join(home, p) : home
  }

  static sourcePath(p?: string, source?: string, root?: string): string {
    const base = join(root ?? Config.home(), Config.SrcDir, source ?? Config.DefaultSource)
    return p ? join(base, p.replace(/^\//, '')) : base
  }

  projectName(): string {
    const src = Config.sourcePath(undefined, undefined, this.root ?? undefined)
    return this._path.replace(src, '').replace(/^[/\\]/, '')
  }

  isDevProject(): boolean {
    return Object.keys(this.raw).length > 0
  }

  static read(path: string, root?: string): Config {
    return new Config(path, Config.parseYaml(path), undefined, root ?? null)
  }

  static fromPath(path: string): Config {
    return Config.read(path)
  }

  static fromProjectName(name: string | ProjectDefinition, root?: string): Config {
    const resolvedRoot = root ?? join(process.cwd(), Config.DevDir)
    const projectStr = typeof name === 'string' ? name : name.repo
    return Config.read(Config.sourcePath(projectStr, undefined, resolvedRoot), resolvedRoot)
  }

  static fromProjectDefinition(project: ProjectDefinition, root?: string): Config {
    const resolvedRoot = root ?? join(process.cwd(), Config.DevDir)
    return Config.read(Config.sourcePath(project.repo, undefined, resolvedRoot), resolvedRoot)
  }

  private static loadYaml(fullPath: string): RawConfig {
    if (!existsSync(fullPath)) return {}
    try {
      return (yaml.load(readFileSync(fullPath, 'utf8')) as RawConfig) ?? {}
    } catch (e) {
      throw new UserException(`Failed to parse ${fullPath}: ${String(e)}`)
    }
  }

  private static mergeConfigs(base: RawConfig, local: RawConfig): RawConfig {
    if (Object.keys(local).length === 0) return base

    const merged: RawConfig = { ...base, ...local }

    // Deep-merge record types so local can override individual keys
    const env = { ...base.env, ...local.env }
    if (Object.keys(env).length > 0) merged.env = env

    const commands = { ...base.commands, ...local.commands }
    if (Object.keys(commands).length > 0) merged.commands = commands

    const sites = { ...base.sites, ...local.sites }
    if (Object.keys(sites).length > 0) merged.sites = sites

    const mergedServe = Config.mergeServe(base.serve, local.serve)
    if (mergedServe !== undefined) merged.serve = mergedServe

    // For steps/up, local fully replaces base (procedural ordering is ambiguous to merge)
    const mergedSteps = local.steps ?? local.up ?? base.steps ?? base.up
    if (mergedSteps !== undefined) merged.steps = mergedSteps

    // Merge and deduplicate projects
    const projects = [...new Set([...(base.projects ?? []), ...(local.projects ?? [])])]
    if (projects.length > 0) merged.projects = projects

    return merged
  }

  private static mergeServe(
    base: RawConfig['serve'],
    local: RawConfig['serve'],
  ): RawConfig['serve'] {
    if (!local) return base
    if (!base || typeof base === 'string' || typeof local === 'string') return local
    return { ...base, ...local }
  }

  private static parseYaml(path: string): RawConfig {
    const base = Config.loadYaml(join(path, Config.FileName))
    const local = Config.loadYaml(join(path, Config.LocalFileName))
    return Config.mergeConfigs(base, local)
  }

  getServe(): Record<string, RawServe> | string {
    return this.raw.serve ?? {}
  }

  file(): string {
    return this.cwd(Config.FileName)
  }

  localFile(): string {
    return this.cwd(Config.LocalFileName)
  }

  async envs(): Promise<Map<string, string>> {
    const [resolved, prompted] = await this._env.resolve(this.settings.env)
    this.settings.env = prompted

    if (this._env.wasPrompted()) {
      this.writeSettings()
    }

    return resolved
  }

  putenv(key: string, value: string): void {
    this._env.put(key, value)
  }

  isDebug(): boolean {
    return false
  }

  raw_(): RawConfig {
    return this.raw
  }

  isDarwin(): boolean {
    return this._uname === 'Darwin'
  }

  isLinux(): boolean {
    return this._uname === 'Linux'
  }

  isWindows(): boolean {
    return this._uname === 'Windows'
  }

  platform(): string {
    return this._uname
  }
}
