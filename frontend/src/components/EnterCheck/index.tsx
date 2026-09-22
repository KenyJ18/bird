import React, { useEffect } from 'react';
import { Stack, TextField, Button, Grid } from '@mui/material';
import { useRouter } from 'next/router';
import { useAtom } from 'jotai';
import { budgetAtom } from '@/atoms/budget';

export const EnterCheck: React.FC = () => {
    const router = useRouter();
    const [budget, setBudget] = useAtom(budgetAtom);

    // budget未設定（別タブ・URL直打ち等）→ 入力画面へ戻す（bird_design.md §8注意点2）
    useEffect(() => {
        if (budget == null) {
            router.replace('/');
        }
    }, [budget, router]);

    const budgetValue = budget ?? 0;

    const handleNext = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        setBudget(budgetValue);
        void router.push('/search-result');
    };

    const handleCancel = () => {
        void router.push('/');
    };

    return (
        <main>
            <header>
                <h1 className="title" style={{ fontSize: '24px', fontWeight: 'bold', fontFamily: 'Meiryo' }}>予算確認画面</h1>
            </header>
            <form onSubmit={handleNext}>
                <Stack direction="column" spacing={2}>
                    <Grid container spacing={2}>
                        <Grid size={6}>
                            <TextField
                                label="入力した予算"
                                variant="outlined"
                                fullWidth
                                value={budgetValue}
                                slotProps={{ htmlInput: { readOnly: true } }}
                            />
                        </Grid>
                    </Grid>
                    <Grid container spacing={2}>
                        <Grid size={6}>
                            <Button
                                variant="contained"
                                color="primary"
                                type="submit"
                                fullWidth
                            >
                                次へ
                            </Button>
                        </Grid>
                        <Grid size={6}>
                            <Button
                                variant="contained"
                                color="secondary"
                                type="button"
                                onClick={handleCancel}
                                fullWidth
                            >
                                キャンセル
                            </Button>
                        </Grid>
                    </Grid>
                </Stack>
            </form>
        </main>
    );
}

export default EnterCheck;
