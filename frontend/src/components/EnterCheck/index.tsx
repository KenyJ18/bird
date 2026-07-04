import React from 'react';
import { Stack, TextField, Button, Grid } from '@mui/material';
import { useRouter } from 'next/router';

export default function EnterCheckPage() {
    return (
        <div>
            <EnterCheck />
        </div>
    );
}

export const EnterCheck: React.FC = () => {
    const router = useRouter();
    const { budget } = router.query;
    const budgetValue = budget ? parseFloat(budget as string) : 0;

    const handleNext = () => {
        // 次の画面に遷移（例: 物件検索画面など）
        console.log('確定された予算:', budgetValue);
        // router.push('/search'); // 次の画面のパスを指定
        alert(`予算 ${budgetValue} 円で確定しました`);
    };

    const handleCancel = () => {
        // 予算入力画面に戻る
        router.push('/');
    };

    return (
        <form>
            <header>
                <div>
                    <h1 className="title" style={{ fontSize: '24px', fontWeight: 'bold', fontFamily: 'Meiryo' }}>予算確認画面</h1>
                </div>
            </header>
            <body>
                <div>
                    <Stack direction="column" spacing={2}>
                        <Grid container spacing={2}>
                            <Grid item xs={12}>
                                <TextField 
                                    label="入力した予算" 
                                    variant="outlined" 
                                    disabled 
                                    fullWidth 
                                    value={budgetValue}
                                />
                            </Grid>
                        </Grid>
                        <Grid container spacing={2}>
                            <Grid item xs={6}>
                                <Button 
                                    variant="contained" 
                                    color="primary" 
                                    onClick={handleNext}
                                    fullWidth
                                >
                                    次へ
                                </Button>
                            </Grid>
                            <Grid item xs={6}>
                                <Button 
                                    variant="contained" 
                                    color="secondary" 
                                    onClick={handleCancel}
                                    fullWidth
                                >
                                    キャンセル
                                </Button>
                            </Grid>
                        </Grid>
                    </Stack>
                </div>
            </body>
        </form>
    );
}
