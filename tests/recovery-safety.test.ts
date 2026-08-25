import { describe, expect, test } from 'bun:test'
import { mkdirSync, mkdtempSync, symlinkSync } from 'node:fs'
import { tmpdir } from 'node:os'
import { join } from 'node:path'
import { mysqlConfigSchema } from '../src/plugins/core/config/mysql-config.js'
import { isRepairableManagedCheckout } from '../src/plugins/core/steps/clone-step.js'

describe('managed recovery safety', () => {
  test('repairs only real directories below the DEV dependency root', () => {
    const root = mkdtempSync(join(tmpdir(), 'dev-recovery-'))
    const checkout = join(root, 'src/github.com/example/project')
    const outside = join(root, 'outside')
    const link = join(root, 'src/github.com/example/link')
    mkdirSync(checkout, { recursive: true })
    mkdirSync(outside)
    symlinkSync(outside, link)

    expect(isRepairableManagedCheckout(root, checkout)).toBeTrue()
    expect(isRepairableManagedCheckout(root, outside)).toBeFalse()
    expect(isRepairableManagedCheckout(root, link)).toBeFalse()
  })

  test('accepts safe database names and rejects SQL fragments', () => {
    expect(mysqlConfigSchema.safeParse({ databases: ['app_dev', 'app-test'] }).success).toBeTrue()
    expect(mysqlConfigSchema.safeParse({ databases: 'app`; DROP DATABASE mysql; --' }).success).toBeFalse()
  })
})
