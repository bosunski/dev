import { handle, settings, Config as OclifConfig } from '@oclif/core'
import { UserException } from './exceptions.js'
import embeddedManifest from '../oclif.manifest.json'
import pkg from '../package.json'
import * as allCommands from './commands/index.js'

settings.performanceEnabled = false

function isCompiledBinary(): boolean {
  return import.meta.url.startsWith('file:///$bunfs/')
}

async function ensureOclifRoot(): Promise<string> {
  const { tmpdir } = await import('node:os')
  const { join } = await import('node:path')
  const { mkdirSync, writeFileSync, existsSync } = await import('node:fs')

  const root = join(tmpdir(), `dev-oclif-${pkg.version}`)
  if (!existsSync(root)) {
    mkdirSync(root, { recursive: true })
  }

  const pjsonPath = join(root, 'package.json')
  if (!existsSync(pjsonPath)) {
    const stub = {
      name: pkg.name,
      version: pkg.version,
      description: pkg.description,
      type: 'module',
      oclif: pkg.oclif,
      bin: pkg.bin,
    }
    writeFileSync(pjsonPath, JSON.stringify(stub, null, 2))
  }

  // Always write the latest manifest
  const { join: pathJoin } = await import('node:path')
  writeFileSync(pathJoin(root, 'oclif.manifest.json'), JSON.stringify(embeddedManifest, null, 2))

  return root
}

async function main(): Promise<void> {
  const argv = process.argv.slice(2)
  const commandName = argv[0]

  let config: OclifConfig

  if (isCompiledBinary()) {
    const root = await ensureOclifRoot()
    config = await OclifConfig.load({ root, isRoot: true })

    // Inject the bundled command classes into the root plugin's commandCache
    // so OCLIF never tries to load them from disk (which would fail in a compiled binary)
    const rootPlugin = config.getPluginsList()[0]
    if (rootPlugin) {
      // Build a map keyed by the identifier the explicit strategy expects ('default')
      // but the cache is looked up by id — use the named export map directly
      const commandMap: Record<string, unknown> = {}
      for (const [, CmdClass] of Object.entries(allCommands)) {
        const id: string = (CmdClass as any).id
        if (id) commandMap[id] = CmdClass
      }
      ;(rootPlugin as any).commandCache = commandMap
    }
  } else {
    config = await OclifConfig.load(import.meta.url)
  }

  const { run, handle: oclifHandle } = await import('@oclif/core')

  try {
    await run(argv, config)
  } catch (err: unknown) {
    const isNotFound =
      err instanceof Error &&
      (err.constructor.name === 'CLIError' ||
        (err.message.includes('command') && err.message.includes('not found')))

    if (isNotFound && commandName) {
      await runConfigCommand(commandName, argv.slice(1))
    } else {
      await oclifHandle(err as Error)
    }
  }
}

async function runConfigCommand(name: string, args: string[]): Promise<void> {
  const { getDevContext } = await import('./context.js')
  const { dev } = await getDevContext()

  const commands = dev.config.commands()

  const { COMMAND_PROVIDER } = await import('./types/capability.js')
  const pm = dev.getPluginManager()
  const pluginCommands: Record<string, import('./types/config.js').RawCommand> = {}
  for (const cap of pm.getPluginCapabilities<import('./types/capability.js').CommandProvider>(COMMAND_PROVIDER, { dev })) {
    Object.assign(pluginCommands, cap.getConfigCommands())
  }

  const allCmds = { ...pluginCommands, ...commands }
  const command = allCmds[name]

  if (!command) {
    console.error(`Error: command '${name}' not found`)
    process.exit(2)
  }

  let cmdRun = command.run
  if (args.length > 0) {
    if (typeof cmdRun === 'string') {
      args.forEach((arg, i) => {
        cmdRun = (cmdRun as string).replace(new RegExp(`@${i + 1}`, 'g'), arg)
      })
    }
  }

  const proc = await dev.runner.spawn(cmdRun, dev.config.cwd())
  const code = await proc.exited
  process.exit(code)
}

main()
