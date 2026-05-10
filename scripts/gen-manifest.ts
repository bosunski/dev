#!/usr/bin/env bun
/**
 * Generates oclif.manifest.json for compiled binary builds.
 * Bun can't use `oclif manifest` directly since it runs under Node and
 * can't load .ts files. This script runs under Bun instead.
 */

import * as commands from '../src/commands/index.js'
import pkg from '../package.json'

type CachedFlag = {
  aliases?: string[]
  char?: string
  charAliases?: string[]
  combinable?: boolean
  dependsOn?: string[]
  deprecateAliases?: boolean
  deprecated?: unknown
  description?: string
  env?: string
  exclusive?: string[]
  helpGroup?: string
  helpLabel?: string
  hidden?: boolean
  name: string
  noCacheDefault?: boolean
  relationships?: unknown
  required?: boolean
  summary?: string
  type: string
  allowNo?: boolean
  default?: unknown
  delimiter?: string
  hasDynamicHelp?: boolean
  helpValue?: string
  multiple?: boolean
  options?: string[]
}

type CachedArg = {
  default?: unknown
  description?: string
  hidden?: boolean
  multiple?: boolean
  name: string
  noCacheDefault?: boolean
  options?: string[]
  required?: boolean
}

type CachedCommand = {
  aliases: string[]
  args: Record<string, CachedArg>
  deprecateAliases?: boolean
  description?: string
  examples?: unknown[]
  flags: Record<string, CachedFlag>
  hasDynamicHelp?: boolean
  hidden: boolean
  hiddenAliases: string[]
  id: string
  pluginAlias?: string
  pluginName?: string
  pluginType?: string
  state?: string
  strict?: boolean
  summary?: string
  usage?: string | string[]
}

function cacheFlags(cmdFlags: Record<string, any>): Record<string, CachedFlag> {
  return Object.fromEntries(
    Object.entries(cmdFlags ?? {}).map(([name, flag]) => [
      name,
      {
        aliases: flag.aliases,
        char: flag.char,
        charAliases: flag.charAliases,
        combinable: flag.combinable,
        dependsOn: flag.dependsOn,
        deprecateAliases: flag.deprecateAliases,
        deprecated: flag.deprecated,
        description: flag.description,
        env: flag.env,
        exclusive: flag.exclusive,
        helpGroup: flag.helpGroup,
        helpLabel: flag.helpLabel,
        hidden: flag.hidden,
        name,
        noCacheDefault: flag.noCacheDefault,
        relationships: flag.relationships,
        required: flag.required,
        summary: flag.summary,
        ...(flag.type === 'boolean'
          ? { allowNo: flag.allowNo, type: 'boolean' }
          : {
              default: flag.default,
              delimiter: flag.delimiter,
              helpValue: flag.helpValue,
              multiple: flag.multiple,
              options: flag.options,
              type: flag.type ?? 'option',
            }),
      } satisfies CachedFlag,
    ]),
  )
}

function cacheArgs(cmdArgs: Record<string, any> | unknown[]): Record<string, CachedArg> {
  const entries = Array.isArray(cmdArgs)
    ? cmdArgs.map((a: any) => [a.name, a])
    : Object.entries(cmdArgs ?? {})

  return Object.fromEntries(
    entries.map(([name, arg]: [string, any]) => [
      name,
      {
        default: arg.default,
        description: arg.description,
        hidden: arg.hidden,
        multiple: arg.multiple,
        name,
        noCacheDefault: arg.noCacheDefault,
        options: arg.options,
        required: arg.required,
      } satisfies CachedArg,
    ]),
  )
}

function cacheCommand(CmdClass: any): CachedCommand {
  // Walk prototype chain to collect all static properties
  let proto = CmdClass
  const merged: Record<string, any> = {}
  while (proto && proto !== Function.prototype) {
    for (const k of Object.getOwnPropertyNames(proto)) {
      if (!(k in merged)) merged[k] = proto[k]
    }
    proto = Object.getPrototypeOf(proto)
  }

  const flags = merged['flags'] ?? {}
  const args = merged['args'] ?? {}

  return {
    aliases: (merged['aliases'] ?? []).map((a: string) => a.replaceAll(' ', ':')),
    args: cacheArgs(args),
    deprecateAliases: merged['deprecateAliases'],
    description: merged['description'],
    examples: merged['examples'] ?? merged['example'],
    flags: cacheFlags(flags),
    hasDynamicHelp: Object.values(cacheFlags(flags)).some((f) => f.hasDynamicHelp),
    hidden: merged['hidden'] ?? false,
    hiddenAliases: merged['hiddenAliases'] ?? [],
    id: merged['id'],
    pluginAlias: '@bosunski/dev',
    pluginName: pkg.name,
    pluginType: 'core',
    state: merged['state'],
    strict: merged['strict'],
    summary: merged['summary'],
    usage: merged['usage'],
  }
}

const cachedCommands: Record<string, CachedCommand> = {}

for (const [, CmdClass] of Object.entries(commands)) {
  const cached = cacheCommand(CmdClass)
  cachedCommands[cached.id] = cached
}

const manifest = {
  commands: cachedCommands,
  version: pkg.version,
}

await Bun.write('oclif.manifest.json', JSON.stringify(manifest, null, 2) + '\n')
console.log(`Generated oclif.manifest.json with ${Object.keys(cachedCommands).length} commands`)
