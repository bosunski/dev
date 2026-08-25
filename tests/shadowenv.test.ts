import { describe, expect, test } from 'bun:test'
import { shadowenvAsset } from '../src/plugins/core/steps/shadowenv/ensure-shadowenv-step.js'

describe('Shadowenv installer', () => {
  test('selects the official Linux x86_64 release asset', () => {
    expect(shadowenvAsset('linux', 'x64')).toBe('shadowenv-x86_64-unknown-linux-gnu')
  })

  test('selects the official macOS arm64 release asset', () => {
    expect(shadowenvAsset('darwin', 'arm64')).toBe('shadowenv-aarch64-apple-darwin')
  })
})
